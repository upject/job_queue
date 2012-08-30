<?
class Queue {

  protected $name, $db;
  
  public function __construct($name) {
    $this->name = $name;
    $this->db = new SQLite3("/var/lib/job_queue/jobs.db");
    $this->db->busyTimeout(10000);
  }
  
  public function addJob($type, $data) {
    $t = time();
    $sql = "INSERT INTO jobs (type, queue, state, lastUpdate, data) VALUES ('$type', '$this->name', 'pending', $t, '$data');";
    $this->db->exec($sql);
  }

  public function getJobs() {
    $res = $this->db->query("SELECT id, type, state, progress, lastUpdate FROM jobs WHERE state='pending' OR state='running' ORDER BY id");
    $result = array();
    $idx = 0;
    while($row = $res->fetchArray()) {
      $result[$idx] = $row;
      ++$idx;
    }
    return $result;
  }
  
  public function getLast($type) {
    $res = $this->db->query("SELECT id, state, progress, lastUpdate FROM jobs WHERE type='$type' ORDER BY id DESC");
    return $res->fetchArray();
  }
}

function main() {
  $q = new Queue("ImportQueue");
  $q->addJob("test1", "Hohoho");
  $jobs = $q->getJobs();
  //var_dump($jobs);
  while(true) {
    $last = $q->getLast("test1");
    var_dump($last);
    if($last['state'] != 'pending' && $last['state'] != 'running') {
      break;
    }
    sleep(1);
  }

}

main();
?>
