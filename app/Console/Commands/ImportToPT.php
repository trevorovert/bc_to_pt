<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Basecamp\BasecampClient;
use Curl;

class ImportToPT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pivotaltracker:import-from-basecamp 
                            {basecamp_project_id : Specify the ID of the basecamp project} 
                            {pivotal_tracker_project_id : Specify the ID of the pivotal tracker project}
                            {--a|to_dos_assigned_to=  : Optional ability to only import to-do items that are assigned to a specific basecamp user} 
                            {--l|to_do_list_id= : Optional ability to specify which to-do list in Basecamp we will import to-do items from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports to-dos from a project in Basecamp into stories in a Pivotal Tracker Project';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    public $totalAdded = 0;
    public $totalSkipped = 0;
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $bcUsername = $this->ask('What is your Basecamp username (email) ?');
        $bcPassword = $this->secret('What is your Basecamp password');

        $client = \Basecamp\BasecampClient::factory(array(
            'auth' => 'http',
            'username' => $bcUsername,
            'password' => $bcPassword,
            'user_id' =>  3310361,
            'app_name' => 'test application',
            'app_contact' => 'http://trevorovert.com'
        ));
        $bcProjectId = (int)$this->argument('basecamp_project_id');

        //If the "To dos assigned to" option was selected, then we will hit that api function
        if($this->option('to_dos_assigned_to')){
            $lists = $client->getAssignedTodolistsByPerson(array(
                'personId' => (int)$this->option('to_dos_assigned_to')
                ));
            //For loops break down the todo lists into individual todos. Then build the response array.
            foreach($lists as $list){
                foreach($list["assigned_todos"] as $todo){
                    $response[] = $todo;
                }
            }
        }else if($this->option('to_do_list_id')){
            //gathers to-dos from a specified todo list id. Function was custom added to service.php
            $response = $client->getTodosByList(array(
                'projectId' => $bcProjectId,
                'todolistId' => (int)$this->option('to_do_list_id')
                ));
            print_r($response); exit(0);
        }else{
            //No options selected, standard pull by project. This will return all todos in a project.
            //Function was custom added to service.php in basecamp api
            $response = $client->getTodosByProject( array( 
                'projectId' => $bcProjectId, 
             ) );
        }

        //Generate array of stories using bc data
        $insertArray = $this->createOutputArray($response);
        //Curls array into PT.
        $this->insertPT($insertArray);

        $this->info('Job completed');
        $this->info('Number of stories added: ' .$this->totalAdded);
        $this->info('Number of stories skipped due to already existing: '.$this->totalSkipped);

    }
        /*****
        *
        * Generate an array that contains data from basecamp ($list) 
        *       in PT format ($insertArray @return)
        *
        */
        public function createOutputArray($list){
            $listIterator = 0;
            foreach($list as $item){
                $insertArray[$listIterator]["name"] = $item["content"];
                $insertArray[$listIterator]["labels"][] = "BC-IMPORT-".$item["id"];
                $listIterator++;
            }
            return $insertArray;
        }


        /*****
        *
        * Inserts an array into PT stories.
        *
        */
        public function insertPT($insertArray){
            //Project ID for PT from Input
            $ptProjectId = (int)$this->argument('pivotal_tracker_project_id');
            //Api token specific to user
            $apiToken = '8889d0f2904d48794597f604f9a5b526';
            $bar = $this->output->createProgressBar(count($insertArray));
            foreach($insertArray as $insert){
                /*First check if it already exists*/
                 $checkExists = Curl::to("https://www.pivotaltracker.com/services/v5/projects/".$ptProjectId."/stories?with_label=".$insert["labels"][0])
                                ->withHeader('X-TrackerToken: '.$apiToken)
                                ->withHeader('Content-Type: application/json')
                                ->withOption('SSL_VERIFYPEER', false)
                                ->asJson()
                                ->get(); 

                //If it doesn't exist (verified by using the label and ID from bc) then go ahead and insert it.
                if(!$checkExists){
                    $response = Curl::to("https://www.pivotaltracker.com/services/v5/projects/".$ptProjectId."/stories")
                                    ->withHeader('X-TrackerToken: '.$apiToken)
                                    ->withHeader('Content-Type: application/json')
                                    ->withOption('SSL_VERIFYPEER', false)
                                    ->withData($insert)
                                    ->asJson()
                                    ->post(); 
                    //Record how many were added each time.
                    $this->totalAdded++;
                }else{
                    //Record how many we skipped because they were already imported.
                    $this->totalSkipped++;
                }
                $bar->advance();
            }
            $bar->finish();
        }
}
