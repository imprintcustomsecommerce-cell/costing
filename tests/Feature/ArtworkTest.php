<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Artwork attached to a quotation: uploaded, downloaded and removed, and never
 * reachable without signing in.
 */
class ArtworkTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::firstOrCreate(['name' => 'SUPER ADMIN', 'guard_name' => 'web']));
        $this->actingAs($user);

        return $user;
    }

    private function quotation(): Quotation
    {
        return Quotation::create([
            'number' => Quotation::nextNumber(),
            'customer_name' => 'Falcon Riders MC',
            'created_by' => auth()->id(),
        ]);
    }

    public function test_artwork_is_uploaded_against_a_quotation(): void
    {
        Storage::fake('artwork');
        $this->user();
        $quotation = $this->quotation();

        $this->post('/quotations/'.$quotation->id.'/artwork', [
            'file' => UploadedFile::fake()->image('front-logo.png', 800, 600),
            'print_location' => 'Front chest',
            'notes' => 'Front print, revision 2',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $artwork = Artwork::sole();
        $this->assertSame('front-logo.png', $artwork->original_name);
        $this->assertSame('Front print, revision 2', $artwork->notes);
        $this->assertSame('Front chest', $artwork->print_location);
        $this->assertSame($quotation->id, $artwork->quotation_id);
        Storage::disk('artwork')->assertExists($artwork->stored_path);

        // Stored under the quotation, not loose at the disk root.
        $this->assertStringStartsWith('quotations/'.$quotation->id.'/', $artwork->stored_path);
    }

    public function test_several_artwork_files_can_be_uploaded_for_their_own_locations(): void
    {
        Storage::fake('artwork');
        $this->user();
        $quotation = $this->quotation();

        $this->post('/quotations/'.$quotation->id.'/artwork', [
            'files' => [
                UploadedFile::fake()->image('front-logo.png', 800, 600),
                UploadedFile::fake()->image('back-logo.png', 800, 600),
            ],
            'print_locations' => ['Front chest', 'Back'],
            'notes' => ['Primary logo', 'Sponsor logo'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(2, Artwork::count());
        $this->assertDatabaseHas('artworks', ['quotation_id' => $quotation->id, 'original_name' => 'front-logo.png', 'print_location' => 'Front chest']);
        $this->assertDatabaseHas('artworks', ['quotation_id' => $quotation->id, 'original_name' => 'back-logo.png', 'print_location' => 'Back']);
    }

    public function test_a_file_whose_contents_contradict_its_extension_is_refused(): void
    {
        Storage::fake('artwork');
        $this->user();
        $quotation = $this->quotation();

        // A plain text file wearing a .png extension. The third argument sets
        // the reported mime, which is what the contents check reads.
        $this->post('/quotations/'.$quotation->id.'/artwork', [
            'file' => UploadedFile::fake()->create('logo.png', 12, 'text/plain'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Artwork::count());
    }

    public function test_an_unsupported_file_type_is_refused(): void
    {
        Storage::fake('artwork');
        $this->user();
        $quotation = $this->quotation();

        $this->post('/quotations/'.$quotation->id.'/artwork', [
            'file' => UploadedFile::fake()->create('payload.exe', 12),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Artwork::count());
    }

    public function test_artwork_downloads_by_its_original_name(): void
    {
        Storage::fake('artwork');
        $this->user();
        $quotation = $this->quotation();
        $this->post('/quotations/'.$quotation->id.'/artwork', [
            'file' => UploadedFile::fake()->image('front-logo.png'),
        ]);

        $this->get('/artwork/'.Artwork::sole()->id.'/download')
            ->assertOk()
            ->assertDownload('front-logo.png');
    }

    public function test_removing_artwork_deletes_the_file_as_well_as_the_row(): void
    {
        Storage::fake('artwork');
        $this->user();
        $quotation = $this->quotation();
        $this->post('/quotations/'.$quotation->id.'/artwork', [
            'file' => UploadedFile::fake()->image('front-logo.png'),
        ]);
        $artwork = Artwork::sole();
        $path = $artwork->stored_path;

        $this->delete('/artwork/'.$artwork->id)->assertRedirect();

        $this->assertSame(0, Artwork::count());
        Storage::disk('artwork')->assertMissing($path);
    }

    public function test_deleting_a_quotation_takes_its_artwork_rows_with_it(): void
    {
        Storage::fake('artwork');
        $this->user();
        $quotation = $this->quotation();
        $this->post('/quotations/'.$quotation->id.'/artwork', [
            'file' => UploadedFile::fake()->image('front-logo.png'),
        ]);

        $quotation->delete();

        $this->assertSame(0, Artwork::count());
    }

    public function test_artwork_is_not_reachable_without_signing_in(): void
    {
        Storage::fake('artwork');
        $this->user();
        $quotation = $this->quotation();
        $this->post('/quotations/'.$quotation->id.'/artwork', [
            'file' => UploadedFile::fake()->image('front-logo.png'),
        ]);
        $artwork = Artwork::sole();

        // The file lives on a private disk, so a guest gets the login page
        // rather than the artwork.
        auth()->logout();
        $this->get('/artwork')->assertRedirect('/login');
        $this->get('/artwork/'.$artwork->id.'/download')->assertRedirect('/login');
    }

    public function test_the_quotation_page_lists_its_artwork(): void
    {
        Storage::fake('artwork');
        $this->user();
        $quotation = $this->quotation();
        $this->post('/quotations/'.$quotation->id.'/artwork', [
            'file' => UploadedFile::fake()->image('front-logo.png'),
            'notes' => 'Front print',
        ]);

        $this->get('/quotations/'.$quotation->id)
            ->assertOk()
            ->assertSee('front-logo.png')
            ->assertSee('Front print');

        $this->get('/artwork')->assertOk()->assertSee($quotation->number);
    }
}
