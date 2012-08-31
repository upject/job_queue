<?php
abstract class Job {
  
  protected $db, $id;
  
  public function __construct($id) {
    $this->db = new SQLite3("/var/lib/job_queue/jobs.db");
    $this->db->busyTimeout(10000);
    $this->id = $id;
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
    $this->db->query("UPDATE jobs SET state='error', message='$message', lastUpdate=$t WHERE id=$this->id"); 
  }
  
}
