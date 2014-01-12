<?php
/**
 * Logging class for recording events and progress.
 *
 * Generally used to create a single record on start of a process and then update as process progresses.
 * Last ResultMessage/ResultInfo is then either success outcome, an error message, or if hard error occurs will have the
 * last progress update before the failure (and not Success or Failed).
 */
class ProgressLogEntry extends DataObject {
	const ResultMessageStarted = 'Started';
	const ResultMessageProgress = 'Working';
	const ResultMessageSuccess = 'Success';
	const ResultMessageWarning = 'Warning';
	const ResultMessageFailed = 'Failed';

	protected static $action = 'Initialised';

	private static $db = array(
		'Task' => 'Varchar(32)',
		'Action' => "Varchar(32)",
		'Who' => "Varchar(256)",
		'Started' => 'SS_Datetime',
		'Ended' => 'SS_Datetime',
		'IPAddress' => 'Varchar(64)',                                               // of request initiator
		'ResultMessage' => "enum('Started,Working,Success,Failed','Started')",
		'ResultInfo' => 'Varchar(255)'
	);

	private static $summary_fields = array(
		'Task',
		'Action',
		'Who',
		'Ended',
		'ResultMessage',
		'ResultInfo'
	);

	/**
	 * Create log entry with initialisers and write it to the database.
	 *
	 * If $task or $action left out will do possibly expensive call to debug_backtrace to get calling class and function .
	 *
	 * SideEffects:
	 *  Writes object to the database.
	 *
	 * @param $action - what the task action is
	 * @param $task - what task is logging progress
	 * @param string $message defaults to 'Started'
	 * @param null $info any additional info on create
	 * @return ProgressLogEntry
	 */
	public static function create($task = null, $action = null, $message = ProgressLogEntry::ResultMessageStarted, $info = null) {
		if (is_null($task) || is_null($action)) {
			$info = self::get_caller_info();

			if (!$task) {
				$task = $info[0];
			}
			if (!$action) {
				$action = $info[1];
			}
		}
		$logEntry = parent::create(array(
			'Task' => $task,
			'Action' => $action,
			'ResultMessage' => $message,
			'ResultInfo' => $info
		));
		$logEntry->write();
		return $logEntry;
	}

	public static function get_caller_info() {
		$trace = debug_backtrace();
		return array(
			$trace[2]['class'],
			$trace[2]['function']
		);
	}

	/**
	 * Update this with the message, info and write to the database.
	 * @param $message
	 * @param null $info
	 * @return ProgressLogEntry $this
	 */
	protected function update_progress($message, $info = null) {
		parent::update(array(
			'ResultMessage' => $message,
			'ResultInfo' => $info
		));

		$this->write();
		return $this;
	}

    /**
     * Update with ResultMessageProgress as Message
     * @param $info
     * @return ProgressLogEntry
     */
    public function step($info) {
        return $this->update_progress(static::ResultMessageProgress, $info);
    }
    /**
     * Update with ResultMessageSuccess as Message
     * @param $info
     * @return ProgressLogEntry
     */
    public function success($info) {
        return $this->update_progress(static::ResultMessageSuccess, $info);
    }
	/**
	 * Update with ResultMessageWarning as Message
	 * @param $info
	 * @return ProgressLogEntry
	 */
	public function warning($info) {
		return $this->update_progress(static::ResultMessageWarning, $info);
	}    /**
     * Update with ResultMessageFailed as Message
     * @param $info
     * @return ProgressLogEntry
     */
    public function failed($info) {
        return $this->update_progress(static::ResultMessageFailed, $info);
    }

	/**
	 * echos info to the output buffer via format
	 */
	public function output() {
		echo $this->format();
	}

    /**
     * Format for output
     * @param string $prefix
     * @param string $suffix
     * @return string
     */
    public function format($prefix = '', $suffix = '') {
        return "$prefix$this->Ended\t$this->ResultMessage" . ($this->ResultInfo ? ":\t$this->ResultInfo" : '') . "$suffix\n";
    }

    /**
     * No manual creation.
     * @param null $member
     * @return bool
     */
    public function canCreate($member = null) {
        return false;
    }
	/**
	 * No editing.
	 * @param null $member see DataObject
	 * @return bool
	 */
	public function canEdit($member = null) {
		return false;
	}

	/**
	 * No deletion - some other archival strategy should be developed.
	 * @param null $member see DataObject
	 * @return bool
	 */
	public function canDelete($member = null) {
		return false;
	}

	/**
	 * Set the Who, When and IPAddress if not set.
	 *
	 * Updates Ended to now.
	 */
	public function onBeforeWrite() {
		if (!$this->Who && Member::currentUser()) {
			$this->Who = Member::currentUser()->Email;
		}
		if (!$this->Started) {
			$this->Started = date('Y-m-d H:i:s');
		}
		if (!$this->IPAddress) {
			$this->IPAddress = Controller::curr()->getRequest()->getIP();
		}
		$this->Ended = date('Y-m-d H:i:s');

		parent::onBeforeWrite();
	}

}