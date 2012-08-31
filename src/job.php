<?php
abstract class Job {
  
  protected $db, $id;
  
  public function __construct($id) {
    $config = json_decode(file_get_contents("/etc/jobs.cfg"));
    if ($config) {
      $this->db = mysqli_connect($config->mysql->host, $config->mysql->user, $config->mysql->password, $config->mysql->db);
      $this->id = $id;
    }
  }
  
  public function __destruct() {
     $this->db->close();
  }
  
  protected function start() {
    $t = time();
    $this->db->query("UPDATE jobs SET state='running', lastUpdate=$t WHERE id=$this->id");
  }
   
  protected function update($progress, $message = "") {
    $t = time();
    $this->db->query("UPDATE jobs SET progress=$progress, message='$message', lastUpdate=$t WHERE id=$this->id");
  }

  protected function finish() {
    $t = time();
    $this->db->query("UPDATE jobs SET state='done', lastUpdate=$t WHERE id=$this->id");
  }
  
  protected function error($message) {
    $t = time();
    $this->db->query("UPDATE jobs SET state='failure', message='$message', lastUpdate=$t WHERE id=$this->id"); 
  }
  
}
