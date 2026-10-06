<?php require_once(__DIR__ . '/../config.php'); ?>
<!DOCTYPE html>
<?php if (isset($_SESSION['MM_Usuario'])): ?>
<html lang="pt-BR">
<?php else: ?>
<html lang="pt-BR" class="has-navbar-fixed-top">
<?php endif; ?>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <meta name="pf-csrf" content="<?= pf_h($pf_csrf) ?>">
    <title>MK - AUTH :: <?php echo isset($Manifest->{'name'}) ? $Manifest->{'name'} . " - V " . $Manifest->{'version'} : 'Painel Financeiro'; ?></title>

    <!-- Grid isolado: NAO usar bootstrap.min.css inteiro, o reset dele vaza para o topo.php -->
    <link href="css/vendor/grid-utilities.css" rel="stylesheet" type="text/css" />
    <link href="../../estilos/mk-auth.css" rel="stylesheet" type="text/css" />
    <link href="../../estilos/font-awesome.css" rel="stylesheet" type="text/css" />
    <link href="../../estilos/bi-icons.css" rel="stylesheet" type="text/css" />

    <!-- jQuery vem do core e SEMPRE antes do mk-auth.js -->
    <script src="../../scripts/jquery.js"></script>
    <script src="../../scripts/mk-auth.js"></script>

    <link href="css/pfin.css?v=<?= (int) @filemtime(__DIR__ . "/../css/pfin.css") ?>" rel="stylesheet" type="text/css" />
    <!-- Chart.js 4.4.1 (MIT) empacotado no addon: funciona sem internet -->
    <script src="js/vendor/chart.umd.min.js"></script>
    <script src="js/pf-ui.js?v=<?= (int) @filemtime(__DIR__ . "/../js/pf-ui.js") ?>"></script>
    <script src="js/pf-graficos.js?v=<?= (int) @filemtime(__DIR__ . "/../js/pf-graficos.js") ?>"></script>
</head>
