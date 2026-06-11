<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessMembersImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // Allow 1 hour for the job to run

    protected $filePath;
    protected $organizationId;
    protected $prefix;

    public function __construct($filePath, $organizationId, $prefix)
    {
        $this->filePath = $filePath;
        $this->organizationId = $organizationId;
        $this->prefix = $prefix;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(3600);

        try {
            \Maatwebsite\Excel\Facades\Excel::import(
                new \App\Imports\MembersImport($this->organizationId, $this->prefix), 
                storage_path('app/' . $this->filePath)
            );
        } finally {
            // Delete the file after processing to save space
            if (Storage::exists($this->filePath)) {
                Storage::delete($this->filePath);
            }
        }
    }
}
