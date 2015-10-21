<?php session_start(); $_SESSION['CustomForm'] = false;?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<style type="text/css" media ="all">
	@import url('css/style.css'); 
	</style>
</head>
<body>
<?php
    include 'list.php';
    echo '<table>';
    echo '<tr><th></th><th>Пример</th><th>О примере</th><th>Файлы</th></tr>';

    foreach($example_list as $example)
        echo '<tr>'.'<div>'
             . '<td>' . $example['id'] . '</td>'
             . '<td>'
             . '<form method ="GET" action="/'.$example['url'].'">'
             . '<input type="submit" name="button" value="'.$example['title'].'">'
             . '</form>'
             . '</td>'
             . '<td>' . '<div>' . $example['about'] . '</div>' . '</td>'
             . '<td>' . '<div>'. $example['files'] . '</div>' . '</td>'
             .'</tr>';

    echo '</div>'.'</table></body>';

?>
</body>
</html>
