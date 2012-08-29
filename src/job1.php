<?
$db = new SQLite3("../data/jobs.db");
$db->busyTimeout(60000);

$max_count = 10;
$count = 0;
$id = 0;

function step() {
  global $count;
  print("job1: " . $count . "\n");
  sleep(1);
}

function update() {
  global $db;
  global $id;
  global $count;
  global $max_count;

  $t = time();
  $progress = ($count / $max_count) * 100;
  $db->query("UPDATE jobs SET progress=$progress, lastUpdate=$t WHERE id=$id");
}

function finish() {
  global $db;
  global $id;

  $t = time();
  $db->query("UPDATE jobs SET state='done', lastUpdate=$t WHERE id=$id");
}

function run() {
  global $db;
  global $id;
  global $count;
  global $max_count;
  
  $t = time();
  $db->query("UPDATE jobs SET state='running', lastUpdate=$t WHERE id=$id");

  $count = 0;
  while($count < $max_count) {
    ++$count;
    step();
    update();
  }
  finish();
}

function main($argc, $argv) {
  global $id;
  
  if($argc > 2) {
    $id = $argv[1];
    run();
  } else {
    print("Usage: job1 <id>\n");
  }
  exit(0);
}

main($argc, $argv);
?>
