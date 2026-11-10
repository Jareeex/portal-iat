<?php

namespace Database\Seeders;

use App\Models\TrainingProgram;
use App\Models\TrainingType;
use Illuminate\Database\Seeder;

/**
 * Daftar program dari file "2024 - PROGRAM TRAINING DEPT OPERATION" (sheet ALL), dipetakan ke item training
 * (training_types) yang jadi sumber plan. Program tanpa pemetaan (Initial Type Rating, dll) tetap muncul;
 * crew-nya ditambahkan manual ke plan. Jalankan SETELAH TrainingRecordSeeder.
 */
class TrainingProgramSeeder extends Seeder
{
    public function run(): void
    {
        $t = fn (?array $groups, ?string $section, ?string $name = null, ?string $like = null) => TrainingType::query()
            ->when($groups, fn ($q) => $q->whereIn('group', $groups))
            ->when($section, fn ($q) => $q->where('section', $section))
            ->when($name, fn ($q) => $q->where('name', $name))
            ->when($like, fn ($q) => $q->where('name', 'like', $like))
            ->pluck('id')->all();

        $ppcSim = 'Pilot Proficiency Check%Simulator%';
        $emb = ['fw_atr', 'fw_rss'];

        $rows = [
            // Rotary Wing
            ['rw', 'PROFFCHEK Simulator EC155B1', $t(['rw'], 'EC 155 B1', null, $ppcSim)],
            ['rw', 'Initial Type Rating Simulator EC155B1', []],
            // Fixed Wing
            ['fw', 'PROFFCHECK Simulator ATR 42-500', $t(['fw_atr'], 'ATR 42-500', null, $ppcSim)],
            ['fw', 'Initial Type Rating ATR 42-500', []],
            ['fw', 'PROFFCHECK Simulator EMB135BJ', $t($emb, 'EMB 135 BJ', null, $ppcSim)],
            ['fw', 'Initial Type Rating EMB 135', []],
            ['fw', 'Line Check ATR 42-500', $t(['fw_atr'], 'ATR 42-500', 'Line Check')],
            ['fw', 'Line Check EMB 135', $t($emb, 'EMB 135 BJ', 'Line Check')],
            // FA
            ['fa', 'Rec Training Type Rating ATR 42 for FA', $t(['fa'], null, null, 'Licence%Recurrent')],
            ['fa', 'Comp Check Type Rating ATR 42 for FA', $t(['fa'], null, null, 'Licence%Competency%')],
            // FOO & HLO
            ['foo', 'Training Recurrent HLO Licence', $t(['foo_hlo'], null, null, 'HLO%Expiry')],
            ['foo', 'Training Initial HLO Licence', []],
            ['foo', 'Training Recurrent DG Licence Type', $t(['foo_hlo'], 'Mandatory', 'Dangerous Goods')],
            ['foo', 'Training Recurrent ACO Licence (Rated)', $t(['foo_hlo'], null, null, 'ACO%Expiry')],
            ['foo', 'Recurrent Type Rating ATR 42 for FOO', $t(['foo_hlo'], 'Licence', 'Recurrent')],
            ['foo', 'Comp. Check Type Rating ATR 42 for FOO', $t(['foo_hlo'], 'Licence', 'Competency Check')],
            ['foo', 'Familiarization Flight Type Rating ATR 42', $t(['foo_hlo'], 'Licence', 'Familiarization Flight')],
            ['foo', 'Company Checker ATR 42/72 for FOO', []],
            // Mandatory
            ['mandatory', 'CRM', $t(null, null, 'CRM')],
            ['mandatory', 'DRM', $t(null, null, 'DRM')],
            ['mandatory', 'WINDSHEAR', $t(null, null, 'Windshear')],
            ['mandatory', 'DGR', $t(null, null, 'Dangerous Goods')],
            ['mandatory', 'AVSEC', $t(null, null, 'Aviation Security')],
            ['mandatory', 'EMERGENCY DRILL', $t(null, null, 'Emergency Drill (CET)')],
            ['mandatory', 'HUET', $t(null, null, 'HUET')],
            ['mandatory', 'NEP ENGLISH LANGUAGE PROFICIENCY', $t(null, null, 'English Proficiency')],
            ['mandatory', 'RVSM', $t(null, null, 'RVSM')],
            ['mandatory', 'ALAR / CFIT', $t(null, null, 'ALAR / CFIT')],
            ['mandatory', 'PBN', $t(null, null, 'PBN')],
            // Additional
            ['additional', 'CCP Course', $t(null, null, 'CCP or CC')],
            ['additional', 'SMS (Safety Management System)', $t(null, null, 'SMS')],
        ];

        foreach ($rows as $i => [$category, $name, $typeIds]) {
            $program = TrainingProgram::updateOrCreate(['category' => $category, 'name' => $name], ['sort_order' => $i]);
            $program->types()->sync($typeIds);
        }
    }
}
