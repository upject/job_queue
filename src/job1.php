<?
include_once("job.php");

class MyJob extends Job {
  private $interval;
  private $max_count;
  private $count;
  
  public function __construct($id, $interval, $max_count) {
    parent::__construct($id);
    $this->interval = $interval;
    $this->max_count = $max_count;
  }
  
  private function step() {
    //sleep($this->interval);
    usleep(100);

    ++$this->count;
    print("job1: id = " . $this->id . ", count= " . $this->count . "\n");
    $progress = $this->count / $this->max_count;
    $this->update($progress);
  }
  
  public function run() {
    $this->count = 0;
    $this->start();
    while($this->count < $this->max_count) {
      $this->step();
    }
    $this->finish();
  }

}
function main($argc, $argv) {
  if($argc > 1) {
    $id = $argv[1];
    $j = new MyJob($id, .1, 10000);
    $j->run();
  } else {
    print("Usage: job1 <id>\n");
  }
  exit(0);
}

main($argc, $argv);
?>
