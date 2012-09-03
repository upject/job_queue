<?
class Queue {

  protected $name, $db;
  
  public function __construct($name) {
    $this->name = $name;
    $config = json_decode(file_get_contents("/etc/jobs.cfg"));
    if ($config)
      $this->db = mysqli_connect($config->mysql->host, $config->mysql->user, $config->mysql->password, $config->mysql->db);
  }
  
  public function __destruct() {
     $this->db->close();
  }
  
  public function addJob($type, $data) {
    $t = time();
    $sql = "INSERT INTO jobs (type, queue, state, lastUpdate, data) VALUES ('$type', '$this->name', 'pending', $t, '$data');";
    $this->db->query($sql);
  }

  public function getJobs() {
    $res = $this->db->query("SELECT id, type, state, progress, lastUpdate FROM jobs WHERE state='pending' OR state='running' ORDER BY id");
    $result = array();
    $idx = 0;
    while($row = $res->fetch_assoc()) {
      $result[$idx] = $row;
      ++$idx;
    }
    return $result;
  }
  
  public function getLast($type) {
    $res = $this->db->query("SELECT id, state, progress, lastUpdate FROM jobs WHERE type='$type' ORDER BY id DESC");
    return $res->fetch_assoc();
  }
  
  public function getCurrent($type) {
    $res = $this->db->query("SELECT id, state, progress, message, lastUpdate FROM jobs WHERE type='$type' AND (state='running' OR state='pending') ORDER BY lastUpdate DESC");
    if($res->num_rows <= 0) {
      $res = $this->db->query("SELECT id, state, progress, message, lastUpdate FROM jobs WHERE type='$type' AND (state not in ('running','pending')) ORDER BY lastUpdate DESC");
    }
    return $res->fetch_assoc();
  }
}

function main() {
  $q = new Queue("ImportQueue");
  $q->addJob("test1", '{"bla": 1, "blupp": 2}');
  $jobs = $q->getJobs();
  //var_dump($jobs);
  while(true) {
    $current = $q->getCurrent("test1");
    var_dump($current);
    if($current['state'] != 'pending' && $current['state'] != 'running') {
      break;
    }
    sleep(1);
  }

}

if ($_SERVER["SCRIPT_FILENAME"]=="") // if not running within apache 
  main($argc, $argv);
?>
