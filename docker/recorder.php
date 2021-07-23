<?php
$modules = $scenario->current('modules');
$sessionId = $modules['WPWebDriver']->webDriver->getSessionID();
$hostname = $_ENV['HOSTNAME'];
$filters = json_encode([
    'volume' => [$_ENV['VIDEO_OUTPUT_DIR']],
    'ancestor' => ['aivec/video-recorder'],
]);
exec(
    "sudo curl --unix-socket /var/run/docker.sock \
    http://$hostname/containers/json -gG -XGET \
    --data-urlencode 'filters=$filters'",
    $output
);
$videoContainerId = json_decode($output[0], true)[0]['Id'];
$size = '1920x1080';
$framerate = 15;
$codec = 'libx264';
$pixelFormat = 'yuv420p';
$currentRecordingName = 'can-make-simple-purchase.mp4';
$ffmpegCMD = "ffmpeg -y \
    -f x11grab \
    -video_size $size \
    -r $framerate \
    -i browser:99 \
    -vcodec $codec \
    -pix_fmt $pixelFormat \
    /data/$currentRecordingName";
$ffmpegCMD = "ffmpeg -y -vv \
    -f x11grab \
    -video_size \$VIDEO_SIZE \
    -r \$FRAME_RATE \$INPUT_OPTIONS \
    -i \$BROWSER_CONTAINER_NAME:0.0 \
    -codec:v \$CODEC \$PRESET \
    -pix_fmt yuv420p \
    -filter:v \"pad=ceil(iw/2)*2:ceil(ih/2)*2\" \
    \"/data/$currentRecordingName\"";
$dexecb = [
    'AttachStdin' => false,
    'AttachStdout' => false,
    'AttachStderr' => false,
    'Tty' => false,
    'Cmd' => [
        '/record.sh',
        $currentRecordingName,
    ],
];
$dexecbjson = json_encode($dexecb);
$output = null;
$status = null;
exec(
    "sudo curl --unix-socket /var/run/docker.sock \
    http://$hostname/containers/$videoContainerId/exec \
    -X POST \
    -H \"Content-Type: application/json\" \
    -d '$dexecbjson'",
    $output,
    $status
);
$execId = json_decode($output[0], true)['Id'];
$output = null;
$status = null;
exec(
    "sudo curl --unix-socket /var/run/docker.sock \
    http://$hostname/exec/$execId/start \
    -X POST \
    -H \"Content-Type: application/json\" \
    -d '{}'",
    $output,
    $status
);

$dexecb['Cmd'] = ['sh', '-c', 'pkill -INT -f ffmpeg'];
$dexecbjson = json_encode($dexecb);
$output = null;
$status = null;
exec(
    "sudo curl --unix-socket /var/run/docker.sock \
    http://$hostname/containers/$videoContainerId/exec \
    -X POST \
    -H \"Content-Type: application/json\" \
    -d '$dexecbjson'",
    $output,
    $status
);
$execId = json_decode($output[0], true)['Id'];
$output = null;
$status = null;
exec(
    "sudo curl --unix-socket /var/run/docker.sock \
    http://$hostname/exec/$execId/start \
    -X POST \
    -H \"Content-Type: application/json\" \
    -d '{}'",
    $output,
    $status
);
