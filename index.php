<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Учёт файлов volpi.ru</title>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
    <script src="scripts/pagination.min.js"></script>
    <link href="scripts/pagination.css" rel="stylesheet">
    <!-- Bootstrap CSS (jsDelivr CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <!-- Bootstrap Bundle JS (jsDelivr CDN) -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

</head>
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
?>

<body>
    <h2>Файлы без ссылок</h2>
    <div id="paginationFiles">

    </div>
    <table class="table">
        <thead>
        <tr>
            <th scope="col">Путь до файла</th>
            <th scope="col">Ссылка</th>
            <th scope="col">Размер</th>
            <th scope="col">Дата создания/обновления</th>
            <th scope="col">Статус</th>
            <th scope="col">Удаление</th>
        </tr>
        </thead>
        <tbody id="filesWithoutLinks">

        </tbody>
    </table>

</body>
<script>
    $('#paginationFiles').pagination({
        dataSource: <?= json_encode(range(1,count($files['1'])))?>,
        totalNumber: <?= count($files['1'])?>,
        pageSize: 10,
        showGoInput: true,
        showGoButton: true,
        callback: function (data, pagination) {
            $.ajax({
                url:'/getFilesWithoutLinksAJax.php?page='+pagination.pageNumber,
                success:function (res){
                    $('#filesWithoutLinks').html(res);
                },
                error:function (error){
                    alert(error);
                }
            });
        }
    })
</script>
</html>