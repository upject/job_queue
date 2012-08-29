<?
class Queue {

  protected $name, $db;
  
  public function __construct($name, $sqlite_db) {
    $this->name = $name;
    $this->db = new SQLite3($sqlite_db);
    $this->db->busyTimeout(10000);
    // TODO: check db connection
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
    $res = $this->db->query("SELECT id, state, progress, lastUpdate FROM jobs WHERE state='pending' OR state='running' ORDER BY id DESC");
    return $res->fetchArray();
  }
}

function main() {
  $q = new Queue("ImportQueue", "../data/jobs.db");
  $q->addJob("test1", "Hohoho");
  $jobs = $q->getJobs();
  //var_dump($jobs);
  var_dump($q->getLast("test1"));
}

main();
?>
