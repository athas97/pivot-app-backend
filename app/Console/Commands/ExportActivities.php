<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Activity;

class ExportActivities extends Command
{
    protected $signature = 'export:activities';
    protected $description = 'Export all activities to CSV';

    public function handle()
    {
        $fileName = storage_path('app/public/activities_' . date('Y-m-d_H-i-s') . '.csv');
        $file = fopen($fileName, 'w');

        $columns = [
            'ID', 'Category Name', 'Title', 'Description', 'Time',
            'Instruction', 'Type', 'Subcategory', 'Duration',
            'Tier', 'Cost', 'Location', 'Minimum Age', 'Maximum Age',
            'Neurodivergent Friendly', 'Neurodivergent Notes',
            'Sensory Tags', 'Social Type', 'Energy Level', 'Outcome Tag',
            'Mood Match'
        ];
        fputcsv($file, $columns);

        Activity::with('hobby')->orderBy('id')->chunk(500, function ($activities) use ($file) {
            foreach ($activities as $activity) {
                fputcsv($file, [
                    $activity->id,
                    $activity->hobby?->name,
                    $activity->activity_title,
                    $activity->description,
                    $activity->time,
                    $activity->instruction,
                    $activity->activity_type,
                    $activity->subcategory,
                    $activity->duration_minutes,
                    $activity->tier,
                    $activity->cost,
                    $activity->location,
                    $activity->min_age,
                    $activity->max_age,
                    $activity->neurodivergent_friendly,
                    $activity->neurodivergent_notes,
                    $activity->sensory_tags,
                    $activity->social_type,
                    $activity->energy_level,
                    $activity->outcome_tag,
                    $activity->mood_match === null ? null : json_encode($activity->mood_match),
                ]);
            }
        });

        fclose($file);
        $this->info("Successfully exported to: {$fileName}");
    }
}