<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Traits\FirebaseAuthTrait;

class DriverDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use FirebaseAuthTrait;

    public $user;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        //driver ref
        $driverRef = "drivers/" . $this->user->id . "";
        $firestoreClient = $this->getFirebaseStoreClient();
        $maxDriverOrderAtOnce = (int) setting('maxDriverOrderAtOnce', 1);
        if ($this->user->vehicle) {
            $maxDriverOrderAtOnce = 1;
        }
        //
        try {
            $firestoreClient->addDocument(
                $driverRef,
                [
                    'online' => (int) $this->user->is_online,
                    'free' => $this->user->assigned_orders < $maxDriverOrderAtOnce ? 1 : 0
                ]
            );
        } catch (\Exception $error) {
            try {
                $firestoreClient->updateDocument(
                    $driverRef,
                    [
                        'online' => (int) $this->user->is_online,
                        'free' => $this->user->assigned_orders < $maxDriverOrderAtOnce ? 1 : 0
                    ]
                );
            } catch (\Exception $error) {
                logger("New Docus error", [$error]);
            }
        }
    }
}
