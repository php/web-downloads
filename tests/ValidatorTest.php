<?php
declare(strict_types=1);

use App\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase {
    public function testValidateRequiredField() {
        $validator = new Validator(['name' => 'required']);
        $validator->validate(['name' => 'John']);
        $this->assertTrue($validator->isValid(), 'Validation should pass when required field is present.');
    }

    public function testValidateMissingRequiredField() {
        $validator = new Validator(['name' => 'required']);
        $validator->validate([]);
        $this->assertFalse($validator->isValid(), 'Validation should fail when required field is missing.');
    }

    public function testValidateStringField() {
        $validator = new Validator(['name' => 'string']);
        $validator->validate(['name' => 123]);
        $this->assertFalse($validator->isValid(), 'Validation should fail when required field is missing.');
    }

    public function testValidationRegexField() {
        $validator = new Validator(['date' => 'regex:/\d{4}-\d{2}-\d{2}/']);
        $validator->validate(['date' => '01/01/2025']);
        $this->assertFalse($validator->isValid(), 'Validation should fail when regex does not match.');

        $validator->validate(['date' => '2025-01-01']);
        $this->assertTrue($validator->isValid(), 'Validation should pass when regex matches.');
    }

    public function testValidateUrlField() {
        $validator = new Validator(['website' => 'url']);
        $validator->validate(['website' => 'https://example.com']);
        $this->assertTrue($validator->isValid(), 'Validation should pass for a valid URL.');
    }

    public function testValidateInvalidUrlField() {
        $validator = new Validator(['website' => 'url']);
        $validator->validate(['website' => 'invalid-url']);
        $this->assertFalse($validator->isValid(), 'Validation should fail for an invalid URL.');
    }

    public function testErrorMessages() {
        $validator = new Validator(['website' => 'url']);
        $validator->validate(['website' => 'invalid-url']);
        $errors = $validator->errors();
        $this->assertNotEmpty($errors, 'Errors should not be empty for invalid validation.');
        $this->assertStringContainsString('must be a valid URL', $errors['website'][0], 'Error message should match.');
        $this->assertStringContainsString('website: The website field must be a valid URL.', $validator->__toString());
    }
}
