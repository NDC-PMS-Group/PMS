<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserModelAccessorsTest extends TestCase
{
    public function test_full_name_accessor_includes_middle_name_and_suffix(): void
    {
        $user = new User([
            'first_name' => 'John',
            'middle_name' => 'Quincy',
            'last_name' => 'Public',
            'suffix' => 'Jr.',
        ]);

        $this->assertSame('John Quincy Public Jr.', $user->full_name);
    }

    public function test_initials_accessor_returns_expected_value(): void
    {
        $user = new User([
            'first_name' => 'Maria',
            'middle_name' => 'Luisa',
            'last_name' => 'Santos',
        ]);

        $this->assertSame('MLS', $user->initials);
    }
}

