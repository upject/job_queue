<?
include_once("job.php");

class MyJob extends Job {
  private $interval;
  private $max_count;
  private $count;
  private $data;
  
  public function __construct($id, $interval, $max_count, $data) {
    parent::__construct($id);
    $this->interval = $interval * 1000000;
    $this->max_count = $max_count;
    $this->data = $data;
  }
  
  public function __destruct() {
     parent::__destruct();
  }
  
  private function step() {
    usleep($this->interval);

    ++$this->count;
    print("job1: id = " . $this->id . ", count= " . $this->count . "data = " . var_export($this->data, true) . "\n");
    $progress = $this->count / $this->max_count * 100;
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
    $data = "";
    if($argc > 2) {
      print("Trying to decode: $argv[2]");
      $data = json_decode($argv[2]);
      var_dump($data);
    }
    $j = new MyJob($id, 0.1, 100, $data);
    $j->run();
  } else {
    print("Usage: job1 <id>\n");
  }
  exit(0);
}

main($argc, $argv);
?>
