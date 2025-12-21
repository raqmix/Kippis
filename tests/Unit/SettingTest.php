<?php

namespace Tests\Unit;

use App\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_setting_value(): void
    {
        Setting::create([
            'key' => 'test_key',
            'value' => 'test_value',
            'type' => 'string',
            'group' => 'general',
        ]);

        $value = Setting::get('test_key');
        $this->assertEquals('test_value', $value);
    }

    public function test_can_get_setting_with_default(): void
    {
        $value = Setting::get('non_existent_key', 'default_value');
        $this->assertEquals('default_value', $value);
    }

    public function test_can_set_setting_value(): void
    {
        Setting::set('test_key', 'test_value', 'string', 'general');

        $this->assertDatabaseHas('settings', [
            'key' => 'test_key',
            'value' => 'test_value',
            'type' => 'string',
            'group' => 'general',
        ]);
    }

    public function test_can_update_existing_setting(): void
    {
        Setting::set('test_key', 'initial_value', 'string', 'general');
        Setting::set('test_key', 'updated_value', 'string', 'general');

        $value = Setting::get('test_key');
        $this->assertEquals('updated_value', $value);
        $this->assertDatabaseCount('settings', 1);
    }

    public function test_can_handle_boolean_values(): void
    {
        Setting::set('working_application', true, 'boolean', 'application');
        
        $value = Setting::get('working_application');
        $this->assertTrue($value);
        
        Setting::set('working_application', false, 'boolean', 'application');
        $value = Setting::get('working_application');
        $this->assertFalse($value);
    }

    public function test_can_handle_json_values(): void
    {
        $jsonData = ['key1' => 'value1', 'key2' => 'value2'];
        Setting::set('json_setting', $jsonData, 'json', 'general');
        
        $value = Setting::get('json_setting');
        $this->assertEquals($jsonData, $value);
    }

    public function test_can_handle_number_values(): void
    {
        Setting::set('number_setting', 123, 'number', 'general');
        
        $value = Setting::get('number_setting');
        $this->assertEquals(123, $value);
        $this->assertIsInt($value);
    }
}
