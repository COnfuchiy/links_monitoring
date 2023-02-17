<?php
require 'components/SiteParser.php';

try {
    $files = SiteParser::getFile(
        Config::FILES_WITHOUT_LINKS_FILENAME,
        ['files' => 'array'],
        Config::FILE_TYPE_RESULT
    );
    $files = $files['files'];
} catch (Exception $e) {
    http_response_code(400);
    die($e->getMessage());
}
$type = $_GET['type'] ? (int)$_GET['type'] : 1;
$page = $_GET['page'] ? (int)$_GET['page'] : 1;
if (!isset($files[$type])) {
    http_response_code(400);
    die('Нет такого типа');
} else {
    $files = $files[$type];
}
$itemCount = 10;
if (($page * 10) + 10 > count($files)) {
    $itemCount = ($page * 10 + 10) - count($files);
}
$outputFiles = array_slice($files, $page * 10, $itemCount);
?>
<?php
foreach ($outputFiles as $file):?>
<tr>
    <td>
        <?= $file['path'] ?>
    </td>
    <td>
        <a href="<?= $file['href'] ?>" target="_blank">Открыть</a>
    </td>
    <td><?= $file['sizeAsString'] ?></td>
    <td><?= date('d-m-Y H:i', strtotime($file['createDate'])); ?></td>
    <td><?php
        $requestResult = RequestComponent::request($file['href'],true);
        if($requestResult['status']){
            echo 'Доступен';
        }
        else{
            echo 'Отсутствует';
        }
        ?></td>
             <td></td>
</tr>
<?php
endforeach; ?>