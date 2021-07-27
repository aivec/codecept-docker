<?php

namespace Codeception\Module;

use Codeception\Module;
use Codeception\TestInterface;
use Exception;

/**
 * Module for recording a video for each test, where the name of the video is
 * the name of the test with the test pass status appended (`passed` or `failed`).
 */
class SelenoidVideoRecorder extends Module
{
    /**
     * WordPress Docker container hostname
     *
     * @var string|null
     */
    protected $hostname = null;

    /**
     * The container ID for the `aivec/selenoid-video-recorder` image
     *
     * @var string|null
     */
    protected $videoContainerId = null;

    /**
     * Video file name
     *
     * @var string
     */
    protected $videoFileName;

    /**
     * Video file extension
     *
     * @var string
     */
    protected $videoFileExt = 'mp4';

    /**
     * Map of tests and their corresponding pass statuses
     *
     * @var array
     */
    protected $testPassStatusMap = [];

    /**
     * Registers a Docker exec command and returns the exec ID
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param array $payload
     * @return string
     */
    public function dockerExecRegisterCommand($payload) {
        $payload = array_merge(
            [
                'AttachStdin' => false,
                'AttachStdout' => false,
                'AttachStderr' => false,
                'Tty' => false,
                'Cmd' => [],
            ],
            $payload
        );

        $output = null;
        $status = null;
        $dexecbjson = json_encode($payload);
        exec(
            "sudo curl -s --unix-socket /var/run/docker.sock \
            http://{$this->hostname}/containers/{$this->videoContainerId}/exec \
            -X POST \
            -H \"Content-Type: application/json\" \
            -d '$dexecbjson'",
            $output,
            $status
        );
        $execId = json_decode($output[0], true)['Id'];

        return $execId;
    }

    /**
     * Starts a Docker exec command given an exec ID
     *
     * Returns the output and status of the exec command
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $execId
     * @param array  $payload
     * @return (array|int)[]
     */
    public function dockerExecStartCommand($execId, $payload = []) {
        $output = null;
        $status = null;
        exec(
            "sudo curl -s --unix-socket /var/run/docker.sock \
            http://{$this->hostname}/exec/{$execId}/start \
            -X POST \
            -H \"Content-Type: application/json\" \
            -d '{}'",
            $output,
            $status
        );

        return [
            'output' => $output,
            'status' => $status,
        ];
    }

    /**
     * Retrieves and sets the video recorder container ID if not already set
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function setup() {
        if ($this->videoContainerId === null) {
            $this->videoContainerId = $this->getVideoContainerId();
        }
    }

    /**
     * Retrieves the video recorder container ID
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string|null
     */
    public function getVideoContainerId() {
        $modules = $this->getModules();
        if (isset($modules['WPWebDriver'])) {
            $this->wd = $modules['WPWebDriver'];
        } elseif (isset($modules['WebDriver'])) {
            $this->wd = $modules['WebDriver'];
        }

        $this->hostname = $_ENV['HOSTNAME'];
        $filters = json_encode([
            'volume' => [$_ENV['VIDEO_OUTPUT_DIR']],
            'ancestor' => ['aivec/selenoid-video-recorder'],
        ]);
        exec(
            "sudo curl -s --unix-socket /var/run/docker.sock \
            http://$this->hostname/containers/json -gG -XGET \
            --data-urlencode 'filters=$filters'",
            $output
        );

        if (!isset($output[0])) {
            return null;
        }

        $res = json_decode($output[0], true);
        if (empty($res)) {
            return null;
        }

        return $res[0]['Id'];
    }

    /**
     * Kills the ffmpeg process to stop video recording
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    protected function stopVideo() {
        if ($this->videoContainerId === null) {
            return;
        }

        $dexecb = [
            'AttachStdout' => true,
            'Cmd' => ['sh', '-c', 'pkill -INT -f ffmpeg'],
        ];
        $execId = $this->dockerExecRegisterCommand($dexecb);
        $this->dockerExecStartCommand($execId);
    }

    /**
     * Starts recording video for the current test
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param TestInterface $test
     * @return void
     */
    public function _before(TestInterface $test) {
        $this->setup();

        $this->videoFileName = $test->getMetadata()->getName();
        $execId = $this->dockerExecRegisterCommand([
            'Cmd' => [
                '/record.sh',
                "{$this->videoFileName}.{$this->videoFileExt}",
            ],
        ]);

        $this->dockerExecStartCommand($execId);
    }

    /**
     * Sets the test status of the current test to `failed`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param TestInterface $test
     * @param Exception     $fail
     * @return void
     */
    public function _failed(TestInterface $test, $fail) {
        $this->testPassStatusMap[$this->videoFileName] = 'failed';
    }

    /**
     * Sets the test status for the current test to `success` if it didn't fail
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param TestInterface $test
     * @return void
     */
    public function _after(TestInterface $test) {
        $this->stopVideo();
        if (!isset($this->testPassStatusMap[$this->videoFileName])) {
            $this->testPassStatusMap[$this->videoFileName] = 'success';
        }
    }

    /**
     * Waits for video recording to completely finish and then renames the video files with
     * the test pass status appended (`passed` or `failed`)
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function _afterSuite() {
        $trys = 0;
        do {
            $pid = null;
            $dexecb = [
                'AttachStdout' => true,
                'Cmd' => ['sh', '-c', 'pgrep ffmpeg'],
            ];
            $execId = $this->dockerExecRegisterCommand($dexecb);
            $output = $this->dockerExecStartCommand($execId)['output'];
            $pid = !empty($output[0]) ? $output[0] : null;
            $trys++;
            sleep(2);
        } while ($pid !== null && $trys < 5);

        $vidsdir = "{$_ENV['AVC_SRC_DIR']}/tests/_output/video";
        foreach ($this->testPassStatusMap as $videoFileName => $status) {
            $file = "{$vidsdir}/{$videoFileName}.{$this->videoFileExt}";
            $newfile = "{$vidsdir}/{$videoFileName}.{$status}.{$this->videoFileExt}";
            exec("cp {$file} {$newfile}");
        }
    }
}
