#!/usr/bin/python

from time import sleep, time
import sqlite3
import psutil, subprocess

config = {
  'php': '/usr/bin/php',
  'sqlite_db': '../data/jobs.db',
  'dispatch_interval': 5.0,
  'idle_timeout': 10.0,
  'queues': {
    'ImportQueue': {
      'priority': 20
    },
    'MailQueue': {
      'priority': 20
    }
  },
  'jobs': {
    'test1': {
      'src': "job1.php"
     }
  }
}

class Dispatcher():
  
  def __init__(self, config):
    
    self.config = config
    self.suspend_interval = config['dispatch_interval']
    self.max_idle = config['idle_timeout']
    self.queues = config['queues']
    self.jobs = config['jobs']

    # only one active process per queue
    self.locks = {}
    self.conn = self.open_db(config['sqlite_db'])

  def open_db(self, db):
    conn = sqlite3.connect(db)
    if conn == None:
      raise RuntimeError("Could not open connection to job db")
    return conn

  def spawn_processes(self):
    c = self.conn.cursor()

    for q in self.queues.keys():
      # Note: a queue allows only one process at once
      if q in self.locks:
        continue

      c.execute("SELECT id, type, data FROM jobs WHERE queue=? and state='pending' ORDER BY id;", (q,) )
      res = c.fetchone()
    
      if res == None:
        continue

      (id, type, data) = res

      if not type in self.jobs:
        c.execute("UPDATE jobs SET state='rejected' WHERE id=?", (id,))
        continue

      job = self.jobs[type]
      src = job['src']
      priority = self.queues[q]['priority']
  
      # spawn the process and renice the process priority
      args = [self.config['php'], src, str(id), data]
      p = subprocess.Popen(args)  
      process = psutil.Process(p.pid)
      process.nice = priority
      
      # check if process is running
      if not psutil.pid_exists(p.pid):
        c.execute("UPDATE jobs SET state='failure' WHERE id=?", (id,))
        continue
      
      # lock the queue
      self.locks[q] = p

      c.execute("INSERT INTO processes VALUES (?, ?)", (id, p.pid))
      self.conn.commit()

    c.close()

  def check_processes(self):
    c = self.conn.cursor()
    
    #print("Checking processes...")
    #print("locks: %s"%str(self.locks))

    c.execute("SELECT j.id, p.pid, j.state, j.queue, j.lastUpdate FROM jobs as j JOIN processes as p ON j.id=p.id")
    res = c.fetchall()
    
    # TODO: fix case where locks are present but no running process registered
    #   -> sanity
    
    for row in res:
      id = row[0]
      pid = row[1]
      state = row[2]
      queue = str(row[3])
      lastUpdate = row[4]
      t = time()

      # check if process has been finished
      #   - remove entry from processes
      #   - remove lock
      if state == "done":
        print("Found finished process: %d"%id)
        c.execute("DELETE FROM processes WHERE id=?", (id,))
        # just make sure the php script did really terminate
        if queue in self.locks and self.locks[queue].pid == pid:
          # note: this is to avoid zombie processes
          self.locks[queue].wait()
          del self.locks[queue]
        continue
      
      
      # check if process has stalled:
      #   - kill process
      #   - set state='killed'
      #   - remove entry from processes
      #   - remove lock
      #print("...checking timestamp: %d - %d > %d?"%(t,lastUpdate,self.max_idle))
      if t-lastUpdate > self.max_idle:
        print("Killing process: %d, %d" % (id, pid))
        p = psutil.Process(pid)
        p.kill()
        c.execute("UPDATE jobs SET state='killed' WHERE id=?", (id,))
        c.execute("DELETE FROM processes WHERE id=?", (id,))
        if queue in self.locks and self.locks[queue].pid == pid:
          # note: this is to avoid zombie processes
          self.locks[queue].wait()
          del self.locks[queue]
    
    self.conn.commit()
    c.close()

  def run(self):
    # iterate until end of world
    while(True):
      self.check_processes()
      self.spawn_processes()
      sleep(self.suspend_interval)

if __name__ == "__main__":
  dispatcher = Dispatcher(config)
  dispatcher.run()