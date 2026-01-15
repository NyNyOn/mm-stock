<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;

class ApiTokenUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Force SQLite for this test to avoid messing with real DB
        $sqliteConfig = config('database.connections.sqlite');
        config(['database.connections.mysql' => $sqliteConfig]);
        config(['database.connections.depart_it_db' => $sqliteConfig]);

        $pdo = \Illuminate\Support\Facades\DB::connection('sqlite')->getPdo();
        \Illuminate\Support\Facades\DB::connection('mysql')->setPdo($pdo);
        \Illuminate\Support\Facades\DB::connection('depart_it_db')->setPdo($pdo);

        // Run migrations for Sanctum and User
        $this->artisan('migrate');

        // Create Mock User Table (sync_ldap) as it's required by User model
        $schema = \Illuminate\Support\Facades\Schema::connection('sqlite');
        if (!$schema->hasTable('sync_ldap')) {
             $schema->create('sync_ldap', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->string('fullname')->nullable();
                $table->string('username')->nullable();
                $table->string('employeecode')->nullable();
                $table->string('photo_path')->nullable();
                $table->string('access_token')->nullable();
                $table->string('status')->nullable();
                $table->string('department_id')->nullable();
            });
        }

        // Create personal_access_tokens table manually
        if (!$schema->hasTable('personal_access_tokens')) {
            $schema->create('personal_access_tokens', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('tokenable_type');
                $table->unsignedBigInteger('tokenable_id'); // Using bigInt for SQLite compatibility
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['tokenable_type', 'tokenable_id']);
            });
        }
    }

    public function test_api_token_updates_last_used_at()
    {
        // 1. Create User
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // 2. Create Token
        $token = $user->createToken('Test Token');
        $plainTextToken = $token->plainTextToken;

        // Verify initial state
        $this->assertNull($token->accessToken->last_used_at);

        // Create a temporary protected route for testing
        \Illuminate\Support\Facades\Route::middleware('auth:sanctum')->get('/test-auth', function () {
            return response()->json(['message' => 'Authenticated']);
        });

        // 3. Make Request with Token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
            'Accept' => 'application/json',
        ])->getJson('/test-auth');

        // 4. Assert Success
        $response->assertStatus(200);

        // 5. Assert Timestamp Updated
        $token->accessToken->refresh();
        $this->assertNotNull($token->accessToken->last_used_at);
    }
}
