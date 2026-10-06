<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_public_board_and_auth_pages_render(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk()->assertSee('No buses boarding just yet.');
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }
}
