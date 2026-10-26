<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, AuthenticationSession, User};
use App\Services\{AuthenticationSessionService, DeviceInfo};
use Illuminate\Http\Request;
use Illuminate\Session\{ArraySessionHandler, Store};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationSessionTest extends TestCase
{
    private User $user;
    private string $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-25 01:00:00', 'UTC'));
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_08_18_000001_add_accounting_fields_to_users_table.php',
            '2026_08_18_000002_create_applications_and_roles_tables.php',
            '2026_10_25_000001_create_activity_logs_table.php',
            '2026_10_25_000002_create_authentication_sessions_table.php',
        ] as $file) (require database_path('migrations/'.$file))->up();
        $application = DB::table('applications')->insertGetId(['code'=>'ACCOUNTING','name'=>'Accounting']);
        $role = DB::table('roles')->insertGetId(['application_id'=>$application,'code'=>'IT','name'=>'IT']);
        $this->user = User::factory()->create(['name'=>'IT Login','password'=>'login-secret']);
        $this->user->roles()->attach($role);
    }

    private function login(): void
    {
        $this->withHeaders(['User-Agent'=>$this->agent])->withServerVariables(['REMOTE_ADDR'=>'192.0.2.10'])
            ->post(route('login.store'),['email'=>$this->user->email,'password'=>'login-secret'])
            ->assertRedirect(route('dashboard'));
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    public function test_successful_login_records_device_ip_actor_and_time_without_session_token(): void
    {
        $this->login();
        $session = AuthenticationSession::firstOrFail();
        $log = ActivityLog::where('action','login')->firstOrFail();
        $this->assertSame('Komputer Windows · Microsoft Edge',$log->device_name);
        $this->assertSame('192.0.2.10',$log->ip_address);
        $this->assertSame($this->user->id,$log->actor_id);
        $this->assertSame('IT Login',$log->actor_name);
        $this->assertSame('25-10-2026 08:00:00 WIB',$log->after_values['logged_in_at']);
        $this->assertSame($session->id,$log->authentication_session_id);
        $this->assertSame(64,strlen($session->getRawOriginal('session_hash')));
        $this->assertStringNotContainsString('login-secret',$log->toJson());
        $this->assertStringNotContainsString($session->getRawOriginal('session_hash'),$log->toJson());
    }

    public function test_failed_login_is_not_recorded_as_successful_login(): void
    {
        $this->post(route('login.store'),['email'=>$this->user->email,'password'=>'wrong'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('authentication_sessions',0);
        $this->assertDatabaseMissing('activity_logs',['action'=>'login']);
    }

    public function test_manual_logout_records_time_and_reason(): void
    {
        $this->login();
        $this->travel(10)->minutes();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $log = ActivityLog::where('action','logout')->firstOrFail();
        $this->assertSame('25-10-2026 08:10:00 WIB',$log->after_values['logged_out_at']);
        $this->assertSame('Tombol logout',$log->after_values['logout_reason']);
        $this->assertSame('192.0.2.10',$log->ip_address);
        $this->assertGuest();
        $this->artisan('sessions:expire-log')->assertSuccessful();
        $this->assertSame(1,ActivityLog::whereIn('action',['logout','logout_auto'])->count());
    }

    public function test_browser_closed_expiry_records_the_actual_deadline_once(): void
    {
        $this->login();
        $deadline = AuthenticationSession::firstOrFail()->expires_at;
        $this->travel(5)->hours();
        $this->artisan('sessions:expire-log')->assertSuccessful();
        $log = ActivityLog::where('action','logout_auto')->firstOrFail();
        $this->assertTrue($deadline->equalTo($log->occurred_at));
        $this->assertSame('25-10-2026 10:00:00 WIB',$log->after_values['logged_out_at']);
        $this->assertSame('Otomatis: 2 jam tanpa aktivitas',$log->after_values['logout_reason']);
        $this->artisan('sessions:expire-log')->assertSuccessful();
        $this->withCredentials()->postJson(route('session.activity'))->assertUnauthorized();
        $this->assertSame(1,ActivityLog::where('action','logout_auto')->count());
    }

    public function test_active_device_does_not_expire_with_other_device_for_same_account(): void
    {
        $this->login();
        $first = AuthenticationSession::firstOrFail();
        $request = Request::create('/session/activity','POST',[],[],[],['HTTP_USER_AGENT'=>'Mozilla/5.0 (iPhone) Version/17.0 Mobile Safari/604.1','REMOTE_ADDR'=>'192.0.2.20']);
        $store = new Store('second-device',new ArraySessionHandler(120));
        $store->start();
        $request->setLaravelSession($store);
        $request->setUserResolver(fn () => $this->user);
        $service = app(AuthenticationSessionService::class);
        $second = $service->start($request,$this->user);
        $this->assertNotSame($first->id,$second->id);
        $this->travel(119)->minutes();
        $this->assertTrue($service->touch($request));
        $this->travel(2)->minutes();
        $this->assertSame(1,$service->expireDue());
        $this->assertNotNull($first->fresh()->logged_out_at);
        $this->assertNull($second->fresh()->logged_out_at);
        $this->assertTrue($service->touch($request));
    }

    public function test_client_automatic_logout_is_distinguished_from_manual_logout(): void
    {
        $this->login();
        $this->travel(119)->minutes();
        $this->withCredentials()->postJson(route('logout'),['reason'=>'idle_timeout'])->assertRedirect(route('login'));
        $this->assertDatabaseHas('activity_logs',['action'=>'logout_auto']);
        $this->assertDatabaseMissing('activity_logs',['action'=>'logout']);
    }

    public function test_login_logout_logs_and_device_search_are_only_available_to_it(): void
    {
        $this->login();
        $log = ActivityLog::where('action','login')->firstOrFail();
        $this->get(route('it.session-logs.index',['action'=>'login','q'=>'192.0.2.10']))->assertOk()->assertSee('Microsoft Edge')->assertSee('192.0.2.10');
        $this->get(route('it.session-logs.show',$log))->assertOk()->assertSee('25-10-2026 08:00:00 WIB');
        $other = User::factory()->create();
        $role = DB::table('roles')->insertGetId(['application_id'=>DB::table('applications')->value('id'),'code'=>'KASIR','name'=>'Kasir']);
        $other->roles()->attach($role);
        $this->actingAs($other);
        $this->get(route('it.activity-logs.index'))->assertForbidden();
        $this->get(route('it.activity-logs.show',$log))->assertForbidden();
        $this->get(route('it.session-logs.index'))->assertForbidden();
        $this->get(route('it.session-logs.show',$log))->assertForbidden();
    }

    public function test_session_and_data_log_pages_cannot_mix_records(): void
    {
        $this->login();
        $sessionLog = ActivityLog::where('action','login')->firstOrFail();
        $this->user->update(['name'=>'Nama Akun Berubah']);
        $dataLog = ActivityLog::where('action','updated')->firstOrFail();
        $sessions = $this->get(route('it.session-logs.index'))->assertOk()->assertViewHas('isSessionLog',true);
        $this->assertSame(['login'],$sessions->viewData('logs')->pluck('action')->unique()->values()->all());
        $data = $this->get(route('it.activity-logs.index'))->assertOk()->assertViewHas('isSessionLog',false);
        $this->assertSame(['updated'],$data->viewData('logs')->pluck('action')->unique()->values()->all());
        $this->get(route('it.activity-logs.show',$sessionLog))->assertNotFound();
        $this->get(route('it.session-logs.show',$dataLog))->assertNotFound();
        $this->get(route('it.activity-logs.index',['action'=>'login']))->assertSessionHasErrors('action');
    }

    public function test_it_dashboard_counts_accounts_once_and_distinguishes_idle_and_signed_out(): void
    {
        $this->login();
        $active = User::factory()->create(['name'=>'Aktif di perangkat lain']);
        $idle = User::factory()->create(['name'=>'Tidak aktif enam menit']);
        $signedOut = User::factory()->create(['name'=>'Sudah keluar']);
        $disabled = User::factory()->create(['name'=>'Dinonaktifkan','is_active'=>false]);
        foreach ([$this->user,$active,$idle,$signedOut,$disabled] as $account) {
            AuthenticationSession::create([
                'session_hash'=>hash('sha256','extra-device-'.$account->id),'actor_id'=>$account->id,
                'actor_name'=>$account->name,'actor_email'=>$account->email,'device_name'=>'Perangkat uji',
                'logged_in_at'=>now('UTC')->subHour(),
                'last_activity_at'=>$account->is($idle) ? now('UTC')->subMinutes(6) : now('UTC'),
                'expires_at'=>now('UTC')->addHour(),
                'logged_out_at'=>$account->is($signedOut) ? now('UTC') : null,
            ]);
        }
        $dashboard = $this->get(route('dashboard'))->assertOk()->assertViewIs('it.dashboard')
            ->assertViewHas('total',5)->assertViewHas('online',2)->assertViewHas('offline',3)
            ->assertSee('Grafik Akun Online dan Offline')->assertDontSee('Grafik Klaim');
        $this->assertCount(5,$dashboard->viewData('users'));
        $online = $this->get(route('dashboard',['status'=>'online']))->assertOk();
        $this->assertEqualsCanonicalizing([$this->user->id,$active->id],$online->viewData('users')->pluck('id')->all());
        $offline = $this->get(route('dashboard',['status'=>'offline']))->assertOk();
        $this->assertEqualsCanonicalizing([$idle->id,$signedOut->id,$disabled->id],$offline->viewData('users')->pluck('id')->all());
    }

    public function test_phone_model_and_unknown_device_fallback(): void
    {
        $request = Request::create('/','GET',[],[],[],['HTTP_USER_AGENT'=>'Mozilla/5.0 (Linux; Android 13; SM-A525F Build/TP1A) Chrome/130.0 Safari/537.36']);
        $this->assertSame('SM-A525F / Android · Chrome',app(DeviceInfo::class)->name($request));
        $this->assertSame('Perangkat tidak dikenali · Browser tidak dikenali',app(DeviceInfo::class)->name(Request::create('/')));
    }
}
