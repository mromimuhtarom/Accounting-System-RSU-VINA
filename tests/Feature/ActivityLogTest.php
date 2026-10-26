<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, Guarantor, User};
use Illuminate\Support\Facades\{DB, Route};
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    private User $it;
    private int $itRole;
    private int $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_000001_add_accounting_fields_to_users_table.php',
            '2026_08_18_000002_create_applications_and_roles_tables.php',
            '2026_08_18_000003_create_chart_accounts_table.php',
            '2026_08_18_000004_create_master_accounting_tables.php',
            '2026_10_25_000001_create_activity_logs_table.php',
            '2026_10_25_000002_create_authentication_sessions_table.php',
        ] as $file) (require database_path('migrations/'.$file))->up();
        $application = DB::table('applications')->insertGetId(['code'=>'ACCOUNTING','name'=>'Accounting']);
        $this->itRole = DB::table('roles')->insertGetId(['application_id'=>$application,'code'=>'IT','name'=>'IT']);
        $this->cashierRole = DB::table('roles')->insertGetId(['application_id'=>$application,'code'=>'KASIR','name'=>'Kasir']);
        $this->it = User::factory()->create(['name'=>'Petugas IT']);
        $this->it->roles()->attach($this->itRole);
        $this->actingAs($this->it);
    }

    private function guarantor(): Guarantor
    {
        return Guarantor::create(['code'=>'ASR','name'=>'Asuransi Lama','category'=>'PRIVATE_INSURANCE']);
    }

    public function test_insert_edit_and_delete_retain_actor_and_original_data(): void
    {
        $item = $this->guarantor();
        $id = $item->id;
        $item->update(['name'=>'Asuransi Baru']);
        $item->delete();
        $logs = ActivityLog::where('subject_type', Guarantor::class)->orderBy('id')->get();
        $this->assertSame(['created','updated','deleted'], $logs->pluck('action')->all());
        $this->assertNull($logs[0]->before_values);
        $this->assertSame('Asuransi Lama', $logs[0]->after_values['name']);
        $this->assertSame('Asuransi Lama', $logs[1]->before_values['name']);
        $this->assertSame('Asuransi Baru', $logs[1]->after_values['name']);
        $this->assertSame(['name'], $logs[1]->changed_fields);
        $this->assertSame('Asuransi Baru', $logs[2]->before_values['name']);
        $this->assertNull($logs[2]->after_values);
        $this->assertSame((string)$id, $logs[2]->subject_id);
        $this->assertSame('Petugas IT', $logs[2]->actor_name);
        $this->assertSame('Master Data', $logs[2]->menu);
        $this->assertSame('Penjamin', $logs[2]->form_name);
        $this->it->update(['name'=>'Nama IT Baru']);
        $this->assertSame('Petugas IT', $logs[2]->fresh()->actor_name);
        $this->assertDatabaseMissing('guarantors', ['id'=>$id]);
    }

    public function test_password_values_are_redacted_but_password_changes_are_recorded(): void
    {
        $user = User::factory()->create(['password'=>'secret-before']);
        $user->update(['password'=>'secret-after']);
        $log = ActivityLog::where('subject_type',User::class)->where('subject_id',$user->id)->where('action','updated')->firstOrFail();
        $this->assertContains('password',$log->changed_fields);
        $this->assertSame('[Disembunyikan]',$log->before_values['password']);
        $this->assertSame('[Disembunyikan]',$log->after_values['password']);
        $this->assertStringNotContainsString($user->password, $log->toJson());
        $this->assertStringNotContainsString('secret-after', $log->toJson());
        $count = ActivityLog::count();
        $user->update(['last_login_at'=>now(), 'remember_token'=>'private-token']);
        $this->assertSame($count, ActivityLog::count());
    }

    public function test_cashier_changes_are_also_logged_with_the_actual_actor(): void
    {
        $user = User::factory()->create(['name'=>'Kasir Pelaku']);
        $user->roles()->attach($this->cashierRole);
        $this->actingAs($user);
        $item = $this->guarantor();
        $log = ActivityLog::where('subject_type',Guarantor::class)->where('subject_id',$item->id)->firstOrFail();
        $this->assertSame($user->id,$log->actor_id);
        $this->assertSame('Kasir Pelaku',$log->actor_name);
        $this->assertSame('KASIR',$log->actor_roles);
    }

    public function test_it_can_filter_and_read_details_while_cashier_cannot_access_logs(): void
    {
        $this->guarantor()->update(['name'=>'Perubahan']);
        $log = ActivityLog::where('action','updated')->firstOrFail();
        $this->get(route('it.activity-logs.index',['action'=>'updated','menu'=>'Master Data','actor_id'=>$this->it->id]))
            ->assertOk()->assertSee('Perubahan')->assertSee('Petugas IT');
        $this->get(route('it.activity-logs.show',$log))->assertOk()->assertSee('Asuransi Lama')->assertSee('Perubahan');
        $empty = $this->get(route('it.activity-logs.index',['q'=>'tidak-ditemukan']))->assertOk();
        $this->assertCount(0, $empty->viewData('logs'));
        $user = User::factory()->create();
        $user->roles()->attach($this->cashierRole);
        $this->actingAs($user);
        $this->get(route('it.activity-logs.index'))->assertForbidden();
        $this->get(route('it.activity-logs.show',$log))->assertForbidden();
        $this->delete(route('it.activity-logs.show',$log))->assertStatus(405);
    }

    public function test_user_role_changes_have_before_and_after_role_names(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->cashierRole);
        $this->put(route('it.users.update',$user), [
            'name'=>'Nama Diubah','email'=>$user->email,'is_active'=>1,'role_ids'=>[$this->itRole],
        ])->assertSessionHasNoErrors()->assertRedirect(route('it.users.index'));
        $log = ActivityLog::where('subject_type',User::class)->where('subject_id',$user->id)->latest('id')->firstOrFail();
        $this->assertSame(['roles'],$log->changed_fields);
        $this->assertSame('KASIR',$log->before_values['roles'][0]['code']);
        $this->assertSame('IT',$log->after_values['roles'][0]['code']);
        $this->assertSame('it.users.update',$log->route_name);
        $this->assertSame($this->it->id,$log->actor_id);
    }

    public function test_failed_request_rolls_back_both_data_and_log(): void
    {
        Route::middleware(['web','auth'])->post('/_audit-rollback', function () {
            $this->guarantor();
            abort(422, 'Simulated failure');
        });
        $this->postJson('/_audit-rollback')->assertUnprocessable();
        $this->assertDatabaseCount('guarantors',0);
        $this->assertDatabaseCount('activity_logs',0);
    }

    public function test_date_filter_uses_wib_boundaries_and_supports_end_date_only(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 18:00:00','UTC'));
        $this->guarantor();
        $this->get(route('it.activity-logs.index',['date_from'=>'2026-10-25','date_to'=>'2026-10-25']))->assertOk()->assertSee('Asuransi Lama');
        $this->get(route('it.activity-logs.index',['date_to'=>'2026-10-24']))->assertOk()->assertDontSee('Asuransi Lama');
    }
}
