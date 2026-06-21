<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Validator;

class ValidatorTest extends TestCase
{
    public function testRequiredFields(): void
    {
        $data = ['nombre' => 'Juan', 'email' => ''];
        $errors = Validator::required($data, ['nombre', 'email', 'password']);
        
        $this->assertArrayNotHasKey('nombre', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function testEmailValidation(): void
    {
        $this->assertTrue(Validator::email('test@example.com'));
        $this->assertFalse(Validator::email('invalid-email'));
    }

    public function testMinLength(): void
    {
        $this->assertTrue(Validator::minLength('12345678', 8));
        $this->assertFalse(Validator::minLength('1234567', 8));
    }

    public function testMaxLength(): void
    {
        $this->assertTrue(Validator::maxLength('12345', 5));
        $this->assertFalse(Validator::maxLength('123456', 5));
    }

    public function testNumeric(): void
    {
        $this->assertTrue(Validator::numeric(123));
        $this->assertTrue(Validator::numeric('12.3'));
        $this->assertFalse(Validator::numeric('abc'));
    }

    public function testInArray(): void
    {
        $this->assertTrue(Validator::in('admin', ['admin', 'supervisor']));
        $this->assertFalse(Validator::in('user', ['admin', 'supervisor']));
    }

    public function testDateValidation(): void
    {
        $this->assertTrue(Validator::date('2026-06-21'));
        $this->assertFalse(Validator::date('2026-13-32'));
        $this->assertFalse(Validator::date('21/06/2026'));
    }

    public function testIsSecurePassword(): void
    {
        $this->assertTrue(Validator::isSecurePassword('pass1234'));
        $this->assertTrue(Validator::isSecurePassword('SecureP4ss'));
        
        // Too short
        $this->assertFalse(Validator::isSecurePassword('p1'));
        // No numbers
        $this->assertFalse(Validator::isSecurePassword('password'));
        // No letters
        $this->assertFalse(Validator::isSecurePassword('12345678'));
    }
}
