<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AssistantProfile;
use App\Models\User;
use App\Services\AssistantProfileSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssistantProfileTest extends TestCase
{
    private User $it;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['0001_01_01_000000_create_users_table.php', '2026_08_18_000001_add_accounting_fields_to_users_table.php', '2026_08_18_000002_create_applications_and_roles_tables.php', '2026_10_25_000001_create_activity_logs_table.php', '2026_10_30_000001_create_assistant_profiles_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $app = DB::table('applications')->insertGetId(['code' => 'ACCOUNTING', 'name' => 'Accounting']);
        foreach (['IT', 'ACCOUNTING', 'ADMIN_ACCOUNTING', 'KASIR', 'OWNER'] as $role) {
            DB::table('roles')->insert(['application_id' => $app, 'code' => $role, 'name' => $role]);
        }
        Storage::fake('local');
        $this->it = $this->user('IT');
        $this->actingAs($this->it);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->roles()->attach(DB::table('roles')->where('code', $role)->value('id'));

        return $u;
    }

    private function png(int $width = 64, int $height = 64): UploadedFile
    {
        $chunk = fn ($type, $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $content = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress(str_repeat("\0".str_repeat("\x10\x80\x50", $width), $height))).$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent('avatar.png', $content);
    }

    public function test_only_active_it_can_manage_profile_and_navigation_is_role_scoped(): void
    {
        $this->get(route('it.assistant-profile.edit'))->assertOk()->assertSee('Simpan Profil Asisten');
        foreach (['ACCOUNTING', 'ADMIN_ACCOUNTING', 'KASIR', 'OWNER'] as $role) {
            $this->actingAs($this->user($role));
            $this->get(route('it.assistant-profile.edit'))->assertForbidden();
            $this->put(route('it.assistant-profile.update'), ['name' => 'Tidak Boleh', 'lock_version' => 0])->assertForbidden();
        }
        $this->it->update(['is_active' => false]);
        $this->actingAs($this->it);
        $this->get(route('it.assistant-profile.edit'))->assertForbidden();
        $this->assertSame('Sukoco', AssistantProfile::findOrFail(1)->name);
    }

    public function test_name_update_is_persistent_escaped_and_audited(): void
    {
        $this->put(route('it.assistant-profile.update'), ['name' => 'Sukoco Baru', 'lock_version' => 0])->assertSessionHasNoErrors()->assertRedirect(route('it.assistant-profile.edit'));
        $profile = AssistantProfile::findOrFail(1);
        $this->assertSame('Sukoco Baru', $profile->name);
        $this->assertSame(1, $profile->lock_version);
        $this->assertSame($this->it->id, $profile->updated_by);
        $this->get(route('it.assistant-profile.edit'))->assertOk()->assertSee('data-name="Sukoco Baru"', false)->assertSee('Halo, saya Sukoco Baru');
        $log = ActivityLog::where('subject_type', AssistantProfile::class)->where('action', 'updated')->firstOrFail();
        $this->assertSame('Sukoco', $log->before_values['name']);
        $this->assertSame('Sukoco Baru', $log->after_values['name']);
        $this->put(route('it.assistant-profile.update'), ['name' => '<img src=x>', 'lock_version' => 1])->assertSessionHasNoErrors();
        $this->get(route('it.assistant-profile.edit'))->assertSee('&lt;img src=x&gt;', false)->assertDontSee('data-name="<img src=x>"', false);
    }

    public function test_photo_upload_is_private_served_to_authenticated_users_and_removable_after_commit(): void
    {
        $this->put(route('it.assistant-profile.update'), ['name' => 'Sukoco Foto', 'lock_version' => 0, 'photo' => $this->png()])->assertSessionHasNoErrors();
        $profile = AssistantProfile::findOrFail(1);
        $path = $profile->photo_path;
        $this->assertStringStartsWith('assistant-profiles/', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame('image/png', $profile->photo_mime);
        $this->actingAs($this->user('ACCOUNTING'));
        $this->get(route('assistant.avatar', ['v' => $profile->lock_version]))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        auth()->logout();
        $this->get(route('assistant.avatar'))->assertRedirect(route('login'));
        $this->actingAs($this->it);
        $this->put(route('it.assistant-profile.update'), ['name' => 'Sukoco Foto', 'lock_version' => 1, 'remove_photo' => 1])->assertSessionHasNoErrors();
        $this->assertNull(AssistantProfile::findOrFail(1)->photo_path);
        Storage::disk('local')->assertMissing($path);
        $this->get(route('assistant.avatar'))->assertNotFound();
    }

    public function test_stale_update_rejects_and_cleans_new_upload_without_losing_current_photo(): void
    {
        $this->put(route('it.assistant-profile.update'), ['name' => 'Versi Pertama', 'lock_version' => 0, 'photo' => $this->png()])->assertSessionHasNoErrors();
        $path = AssistantProfile::findOrFail(1)->photo_path;
        $this->put(route('it.assistant-profile.update'), ['name' => 'Versi Lama', 'lock_version' => 0, 'photo' => $this->png()])->assertSessionHasErrors('profile');
        $this->assertSame($path, AssistantProfile::findOrFail(1)->photo_path);
        $this->assertSame('Versi Pertama', AssistantProfile::findOrFail(1)->name);
        $this->assertSame([$path], Storage::disk('local')->allFiles('assistant-profiles'));
    }

    public function test_invalid_images_name_and_conflicting_photo_actions_do_not_change_settings(): void
    {
        $this->put(route('it.assistant-profile.update'), ['name' => '', 'lock_version' => 0])->assertSessionHasErrors('name');
        $this->put(route('it.assistant-profile.update'), ['name' => str_repeat('a', 61), 'lock_version' => 0])->assertSessionHasErrors('name');
        foreach ([$this->png(1, 1), UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), UploadedFile::fake()->create('large.png', 2049, 'image/png')] as $file) {
            $this->put(route('it.assistant-profile.update'), ['name' => 'Tidak Disimpan', 'lock_version' => 0, 'photo' => $file])->assertSessionHasErrors('photo');
        }
        $this->put(route('it.assistant-profile.update'), ['name' => 'Konflik', 'lock_version' => 0, 'photo' => $this->png(), 'remove_photo' => 1])->assertSessionHasErrors('photo');
        $this->assertSame('Sukoco', AssistantProfile::findOrFail(1)->name);
        $this->assertSame([], Storage::disk('local')->allFiles('assistant-profiles'));
    }

    public function test_default_profile_is_readable_before_migration_without_creating_a_record(): void
    {
        Schema::drop('assistant_profiles');
        $profile = app(AssistantProfileSettings::class)->current();
        $this->assertSame('Sukoco',$profile->name);
        $this->assertFalse($profile->exists);
    }
}
