<?php

require_once __DIR__.'/Support/FeatureTestHelpers.php';

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
