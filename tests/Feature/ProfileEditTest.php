<?php

use App\Models\College;
use App\Models\Program;
use App\Models\User;

describe('Profile edit', function () {

    it('shows the faculty current program in the program dropdown on load', function () {
        $college = College::factory()->create(['is_active' => true, 'code' => 'CCS']);
        $program = Program::query()->create([
            'college_id' => $college->id,
            'code' => 'BSIT',
            'name' => 'Bachelor of Science in Information Technology',
            'is_active' => true,
        ]);

        $faculty = User::factory()->create([
            'college_id' => $college->id,
            'program_id' => $program->id,
            'is_active' => true,
        ]);
        $faculty->assignRole('faculty');

        $response = $this->actingAs($faculty)
            ->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('profileCollegeProgram', false);
        $response->assertSee('value="'.$program->id.'"', false);
        $response->assertSee('BSIT', false);
        $response->assertSee('selectedProgramId', false);
        $response->assertSee((string) $program->id, false);
        $response->assertSee('name="program_id"', false);
    });

    it('updates program_id when faculty saves profile', function () {
        $college = College::factory()->create(['is_active' => true]);
        $bsit = Program::query()->create([
            'college_id' => $college->id,
            'code' => 'BSIT',
            'name' => 'BS Information Technology',
            'is_active' => true,
        ]);
        $bscs = Program::query()->create([
            'college_id' => $college->id,
            'code' => 'BSCS',
            'name' => 'BS Computer Science',
            'is_active' => true,
        ]);

        $faculty = User::factory()->create([
            'college_id' => $college->id,
            'program_id' => $bsit->id,
            'first_name' => 'Faculty',
            'last_name' => 'User',
            'is_active' => true,
        ]);
        $faculty->assignRole('faculty');

        $this->actingAs($faculty)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'first_name' => 'Faculty',
                'last_name' => 'User',
                'middle_name' => null,
                'college_id' => $college->id,
                'program_id' => $bscs->id,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        expect($faculty->fresh()->program_id)->toBe($bscs->id);
    });

    it('persists and redisplays the selected program after save', function () {
        $college = College::query()->where('code', 'CCS')->first()
            ?? College::factory()->create(['is_active' => true, 'code' => 'CCS', 'name' => 'College of Computer Studies']);

        $bmma = Program::query()->where('code', 'BMMA')->first()
            ?? Program::query()->create([
                'college_id' => $college->id,
                'code' => 'BMMA',
                'name' => 'Bachelor of Multimedia Arts',
                'is_active' => true,
            ]);

        $faculty = User::factory()->create([
            'college_id' => $college->id,
            'program_id' => null,
            'first_name' => 'Faculty',
            'last_name' => 'User',
            'is_active' => true,
        ]);
        $faculty->assignRole('faculty');

        $this->actingAs($faculty)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'first_name' => 'Faculty',
                'last_name' => 'User',
                'middle_name' => null,
                'college_id' => $college->id,
                'program_id' => $bmma->id,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        expect($faculty->fresh()->program_id)->toBe($bmma->id);

        $response = $this->actingAs($faculty->fresh())
            ->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('value="'.$bmma->id.'"', false);
        $response->assertSee('BMMA', false);
        $response->assertSee('selectedProgramId', false);
        $response->assertSee((string) $bmma->id, false);
    });
});
