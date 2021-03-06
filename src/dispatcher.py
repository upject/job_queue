#!/usr/bin/python

import psutil, subprocess, MySQLdb as mdb, sys, json
from time import sleep, time

class Dispatcher():
  
  def __init__(self, config):
    
    self.config = config
    self.suspend_interval = config['dispatch_interval']
    self.max_idle = config['idle_timeout']
    self.queues = config['queues']
    self.jobs = config['jobs']

    # only one active process per queue
    self.locks = {}
    self.conn = self.open_db(config['mysql'])

  def open_db(self, dbconf):
    conn = mdb.connect(dbconf['host'], dbconf['user'], dbconf['password'], dbconf['db'])
    if conn == None:
      raise RuntimeError("Could not open connection to job db")
    print("Connected to DB")
    return conn

  def spawn_processes(self):
    c = self.conn.cursor()
    try:
      for q in self.queues.keys():
        # Note: a queue allows only one process at once
        if q in self.locks:
          continue

        c.execute("SELECT id, type, context, data FROM jobs WHERE queue=%s and state='pending' ORDER BY id;", (q,) )
        res = c.fetchone()
      
        if res == None:
          continue

        (id, type, context, data) = res

        print("Spawning job: id=%d, type=%s"%(id, type))

        if not type in self.jobs:
          c.execute("UPDATE jobs SET state='rejected' WHERE id=%s", (id,))
          continue

        job = self.jobs[type]
        working_dir = job['working_dir']
        src = job['src']
        priority = self.queues[q]['priority']
    
        # spawn the process and renice the process priority
        args = [self.config['php'], src, str(id), data, context]
        p = subprocess.Popen(args, cwd=working_dir)
        process = psutil.Process(p.pid)
        process.nice = priority
        
        # check if process is running
        if not psutil.pid_exists(p.pid):
          c.execute("UPDATE jobs SET state='failure' WHERE id=%d", (id,))
          continue
        
        # lock the queue
        self.locks[q] = p
        
        c.execute("INSERT INTO processes VALUES (%s, %s)", (id, p.pid))
        self.conn.commit()
    except Exception as err:
      print(str(err))

    finally:
      c.close()

  def check_processes(self):
    c = self.conn.cursor()
    
    try:
      #print("Checking processes...")
      #print("locks: %s"%str(self.locks))

      c.execute("SELECT j.id, p.pid, j.state, j.queue, j.lastUpdate FROM jobs as j JOIN processes as p ON j.id=p.id")
      res = c.fetchall()
      
      # TODO: fix case where locks are present but no running process registered
      #   -> sanity
      
      for id, pid, state, queue, lastUpdate in res:
        #convert from unicode to ascii
        queue = str(queue)
        t = time()

        # check if process has been finished
        #   - remove entry from processes
        #   - remove lock
        if state == "done":
          print("Found finished process: %d"%id)
          c.execute("DELETE FROM processes WHERE id=%s", (id,))
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
          try:
            p = psutil.Process(pid)
            p.kill()
          except:
            # remove entry even if kill was not successful
            pass
          c.execute("UPDATE jobs SET state='pending' WHERE id=%s", (id,))
          c.execute("DELETE FROM processes WHERE id=%s", (id,))
          if queue in self.locks and self.locks[queue].pid == pid:
            # note: this is to avoid zombie processes
            try:
              self.locks[queue].wait()
            except:
              pass
            del self.locks[queue]
    except Exception as err:
      print(str(err))

    finally:
      self.conn.commit()
      c.close()

  def run(self):
    # iterate until end of world
    while(True):
      try:
        self.check_processes()
        self.spawn_processes()
      except Exception as err:
        # don't stop on errors
        pass
      sleep(self.suspend_interval)

if __name__ == "__main__":
  config = json.loads(open(sys.argv[1]).read())
  dispatcher = Dispatcher(config)
  dispatcher.run()
