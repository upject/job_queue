<?
class Queue {

  protected $name, $context, $db;
  
  public function __construct($name, $context = "") {
    $this->name = $name;
    $this->context = $context;
    $config = json_decode(file_get_contents("/etc/jobs.cfg"));
    if ($config)
      $this->db = mysqli_connect($config->mysql->host, $config->mysql->user, $config->mysql->password, $config->mysql->db);
  }
  
  public function __destruct() {
     $this->db->close();
  }
  
  public function addJob($type, $data) {
    $t = time();
    $sql = "INSERT INTO jobs (type, context, queue, state, lastUpdate, data) VALUES ('$type', '$this->context', '$this->name', 'pending', $t, '$data');";
    $this->db->query($sql);
  }

  public function getJobs($ignore_context = false) {
    $res = $this->db->query("SELECT id, type, context, state, progress, message, lastUpdate, data FROM jobs WHERE (state='pending' OR state='running') ".($ignore_context?"":"AND context='$this->context'")." ORDER BY id");
    $result = array();
    $idx = 0;
    while($row = $res->fetch_assoc()) {
      $result[$idx] = $row;
      ++$idx;
    }
    return $result;
  }
  
  public function getLast($type) {
    $res = $this->db->query("SELECT id, state, progress, message, lastUpdate, data FROM jobs WHERE type='$type' AND context='$this->context' ORDER BY id DESC");
    return $res->fetch_assoc();
  }
  
  public function getCurrent($type) {
    $res = $this->db->query("SELECT id, state, progress, message, lastUpdate, data FROM jobs WHERE type='$type' AND context='$this->context' AND (state='running' OR state='pending') ORDER BY lastUpdate DESC");
    if($res->num_rows <= 0) {
      $res = $this->db->query("SELECT id, state, progress, message, lastUpdate, data FROM jobs WHERE type='$type' AND context='$this->context' AND (state not in ('running','pending')) ORDER BY lastUpdate DESC");
    }
    return $res->fetch_assoc();
  }
}

function main() {
  $q = new Queue("ImportQueue", "testcontext");
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
