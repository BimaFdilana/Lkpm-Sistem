<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ImportBatchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pic_receives_forbidden_response_for_import_screen(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);

        $this->actingAs($pic)->get(route('imports.index'))->assertForbidden();
    }
}
