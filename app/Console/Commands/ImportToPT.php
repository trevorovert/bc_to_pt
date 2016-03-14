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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //$bcUsername = $this->ask('What is your Basecamp username (email) ?');
        //$bcPassword = $this->secret('What is your Basecamp password');

        $client = \Basecamp\BasecampClient::factory(array(
            'auth' => 'http',
            //'username' => $bcUsername,
           // 'password' => $bcPassword,
            'username' => 'trevor.m.thompson@gmail.com',
            'password'=> 'trev0802',
            'user_id' =>  3310361,
            'app_name' => 'test application',
            'app_contact' => 'http://trevorovert.com'
        ));
        $bcProjectId = (int)$this->argument('basecamp_project_id');

        /*To be defined by user, possibly as an input? */
        $apiToken = '8889d0f2904d48794597f604f9a5b526';
        $ptProjectId = (int)$this->argument('pivotal_tracker_project_id');

        $response = Curl::to("https://www.pivotaltracker.com/services/v5/projects/".$ptProjectId."/stories")
                            ->withHeader('X-TrackerToken: '.$apiToken)
                            ->withOption('SSL_VERIFYPEER', false)
                            ->asJson()
                            ->get();

        print_r($response); exit(0);



        //If the "To dos assigned to" option was selected, then we will hit that api function
        if($this->option('to_dos_assigned_to')){
            $response = $client->getAssignedTodolistsByPerson(array(
                'personId' => (int)$this->option('to_dos_assigned_to')
                ));
        }else if($this->option('to_do_list_id')){
            //gathers to-dos from a specified todo list id
            $response = $client->getTodoList(array(
                'projectId' => $bcProjectId,
                'todolistId' => (int)$this->option('to_do_list_id')
                ));
        }else{
            //No options selected, standard pull by project
            $response = $client->getTodolistsByProject( array( 
                'projectId' => $bcProjectId, 
             ) );
            foreach($response as $list){
                $todo[] = $client->getTodolist(array(
                    'projectId' => $bcProjectId,
                    'todolistId' => $list["id"]
                ));
                 $response = Curl::to("https://www.pivotaltracker.com/services/v5/projects/".$ptProjectId."/stories")
                            ->withHeader('X-TrackerToken: '.$apiToken)
                            ->withOption('SSL_VERIFYPEER', false)
                            ->asJson()
                            ->post();
                //Place 
                
            }
            print_r($todo);
        }
    }
}
