<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Core\Models\Frame;
use App\Core\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FrameApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_frames_returns_only_active_frames(): void
    {
        // Create active and inactive frames
        $activeFrame = Frame::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        
        $inactiveFrame = Frame::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/frames');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'name_ar', 'name_en', 'thumbnail_url'],
                ],
            ]);

        $data = $response->json('data');
        $frameIds = collect($data)->pluck('id')->toArray();
        
        $this->assertContains($activeFrame->id, $frameIds);
        $this->assertNotContains($inactiveFrame->id, $frameIds);
    }

    public function test_get_frames_excludes_expired_frames(): void
    {
        $activeFrame = Frame::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDay(),
        ]);

        $expiredFrame = Frame::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/frames');

        $response->assertStatus(200);
        $data = $response->json('data');
        $frameIds = collect($data)->pluck('id')->toArray();

        $this->assertContains($activeFrame->id, $frameIds);
        $this->assertNotContains($expiredFrame->id, $frameIds);
    }

    public function test_get_frames_excludes_not_yet_started_frames(): void
    {
        $activeFrame = Frame::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $upcomingFrame = Frame::factory()->create([
            'is_active' => true,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $response = $this->getJson('/api/v1/frames');

        $response->assertStatus(200);
        $data = $response->json('data');
        $frameIds = collect($data)->pluck('id')->toArray();

        $this->assertContains($activeFrame->id, $frameIds);
        $this->assertNotContains($upcomingFrame->id, $frameIds);
    }

    public function test_render_frame_with_valid_image_returns_rendered_url(): void
    {
        Storage::fake('public');

        $customer = Customer::factory()->create();
        $frame = Frame::factory()->create([
            'is_active' => true,
            'overlay_path' => 'frames/overlays/test.png',
        ]);

        // Create a dummy overlay PNG file (1x1 transparent PNG)
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('frames/overlays/test.png', $pngData);

        $image = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/frames/render', [
                'frame_id' => $frame->id,
                'image' => $image,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'frame_id',
                    'rendered_url',
                    'original_url',
                    'width',
                    'height',
                ],
            ]);

        $this->assertNotNull($response->json('data.rendered_url'));
    }

    public function test_render_frame_with_inactive_frame_returns_error(): void
    {
        Storage::fake('public');

        $customer = Customer::factory()->create();
        $frame = Frame::factory()->create([
            'is_active' => false,
        ]);

        $image = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/frames/render', [
                'frame_id' => $frame->id,
                'image' => $image,
            ]);

        $response->assertStatus(422);
    }

    public function test_render_frame_with_expired_frame_returns_error(): void
    {
        Storage::fake('public');

        $customer = Customer::factory()->create();
        $frame = Frame::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $image = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/frames/render', [
                'frame_id' => $frame->id,
                'image' => $image,
            ]);

        $response->assertStatus(422);
    }

    public function test_render_frame_with_invalid_file_type_returns_validation_error(): void
    {
        $customer = Customer::factory()->create();
        $frame = Frame::factory()->create([
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/frames/render', [
                'frame_id' => $frame->id,
                'image' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['errors' => ['image']]]);
    }

    public function test_render_frame_with_file_too_large_returns_validation_error(): void
    {
        $customer = Customer::factory()->create();
        $frame = Frame::factory()->create([
            'is_active' => true,
        ]);

        // Create a file larger than 10MB
        $image = UploadedFile::fake()->image('large.jpg')->size(11000); // 11MB

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/v1/frames/render', [
                'frame_id' => $frame->id,
                'image' => $image,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['errors' => ['image']]]);
    }

    public function test_render_frame_creates_frame_render_record(): void
    {
        Storage::fake('public');

        $customer = Customer::factory()->create();
        $frame = Frame::factory()->create([
            'is_active' => true,
            'overlay_path' => 'frames/overlays/test.png',
        ]);

        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('frames/overlays/test.png', $pngData);

        $image = UploadedFile::fake()->image('photo.jpg');

        $this->actingAs($customer, 'api')
            ->postJson('/api/v1/frames/render', [
                'frame_id' => $frame->id,
                'image' => $image,
            ]);

        $this->assertDatabaseHas('frame_renders', [
            'customer_id' => $customer->id,
            'frame_id' => $frame->id,
        ]);
    }
}

