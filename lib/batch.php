<?PHP

class Batch {
	public $batch_id;
	public $start_date = '';
	public $pid = NULL;
	public $counts = array();

	public function __construct ( $batch_id, $params = array() ) {
		$this->batch_id = $batch_id;
		if (isset($params['date'])) {
			$this->start_date = $params['date'];
		}
		if (isset($params['pid'])) {
			$this->pid = $params['pid'];
		}
		if (isset($params['counts'])) {
			$this->counts = $params['counts'];
		}
	}

	public function load($db_conn) {
		$batch_id = $db_conn->real_escape_string($this->batch_id);
		$dbquery = "SELECT b.start, b.process_id, cmd.status, count(*) from batches b left join commands cmd on cmd.batch_id = b.batch_id where b.batch_id = '$batch_id' group by b.start, b.process_id, cmd.status order by start desc";
		$results = $db_conn->query($dbquery);
		while ($row = $results->fetch_row()) {
			$this->start_date = $row[0];
			$this->pid = $row[1];
			$this->counts[$row[2]] = $row[3];
		}
		$results->close();
	}

	public function reset($db_conn) {
		$query_id = $db_conn->real_escape_string($this->batch_id);
		$dbquery = "UPDATE commands SET status = 'READY', message = NULL WHERE status = 'ERROR' and batch_id = '$query_id'";
		$db_conn->query($dbquery);
	}

	public function delete($owner, $db_conn) {
		$query_id = $db_conn->real_escape_string($this->batch_id);
		$query_owner = $db_conn->real_escape_string($owner);
		$dbquery = "DELETE from batches WHERE batch_id = '$query_id' AND owner = '$query_owner'";
		$db_conn->query($dbquery);
	}

	public function start($oauth) {
		$id = $this->batch_id;
		$env_cmds = "BATCH_ID=$id";
		$env_cmds .= " TOKEN_KEY=" . $oauth->gTokenKey;
		$env_cmds .= " TOKEN_SECRET=" . $oauth->gTokenSecret;
		exec("$env_cmds nohup /usr/bin/php run_background.php >> bg.log 2>&1 &");
	}

	public function stop() {
		$pidval = intval($this->pid);
		if ($pidval > 0) {
			posix_kill($pidval, 15);
		}
	}

	public function is_running() {
		$pid = $this->pid;
		if ($pid == NULL) return false;
		if (!  posix_getpgid($pid) ) return false;
		$proc_status_file = "/proc/$pid/status" ;
		$proc_status_data = file_get_contents($proc_status_file);
		$matches = array();
		preg_match_all('/^([^:]+):\s*(.*)$/m', $proc_status_data, $matches);
		$status_map = array_combine($matches[1], $matches[2]);
		if ( preg_match('/^Z/', $status_map['State'] ) ) return false; // Zombie state
		return true;
	}

	public static function batches_for_owner($db_conn, $owner) {
		$dbquery = "SELECT b.batch_id, b.start, b.process_id, cmd.status, count(*) from batches b left join commands cmd on cmd.batch_id = b.batch_id where owner = '$owner' group by b.batch_id, b.start, b.process_id, cmd.status order by start desc";
		$batch_data_list = array();
		$counts = array();
		$results = $db_conn->query($dbquery);
		while ($row = $results->fetch_row()) {
			$batch_id = $row[0];
			if (! isset($counts[$batch_id]) ) {
				$counts[$batch_id] = array();
				$batch_data = array();
				$batch_data['id'] = $batch_id;
				$batch_data['date'] = $row[1];
				$batch_data['pid'] = $row[2];
				$batch_data_list[] = $batch_data;
			}
			$status = $row[3];
			$counts[$batch_id][$status] = $row[4];
		}
		$results->close();
		$batch_list = array();
		foreach ($batch_data_list AS $batch_data) {
			$batch_id = $batch_data['id'];
			$batch_data['counts'] = $counts[$batch_id];
			$batch_list[] = new Batch($batch_id, $batch_data);
		}
		return $batch_list;
	}

	public static function generate_batch_id () {
		return substr(uniqid(), -6); # Last 6 letters of uniqid
	}
}

?>
