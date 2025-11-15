<!doctype html>
<html class="fixed has-top-menu" lang="fr">
	<head>

		<!-- Basic -->
		<meta charset="UTF-8">

		<title>SYSTEME DE GESTION CLINIQUE EYE CENTER</title>

		<meta name="keywords" content="EYE Center" />
		<meta name="description" content="Application EYE Center">
		<meta name="author" content="eye-center.com">

		<!-- Mobile Metas -->
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

		<!-- Web Fonts  -->
		<link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">

		<!-- Vendor CSS -->
		<link rel="icon" href="../img/logo.jpg" type="image/jpg">
		<link rel="stylesheet" href="../vendor/bootstrap/css/bootstrap.css" />
		<link rel="stylesheet" href="../vendor/animate/animate.compat.css">
		<link rel="stylesheet" href="../vendor/font-awesome/css/all.min.css" />
		<link rel="stylesheet" href="../vendor/boxicons/css/boxicons.min.css" />
		<link rel="stylesheet" href="../vendor/magnific-popup/magnific-popup.css" />
		<link rel="stylesheet" href="../vendor/bootstrap-datepicker/css/bootstrap-datepicker3.css" />
		<link rel="stylesheet" href="../vendor/jquery-ui/jquery-ui.css" />
		<link rel="stylesheet" href="../vendor/jquery-ui/jquery-ui.theme.css" />
		<link rel="stylesheet" href="../vendor/bootstrap-multiselect/css/bootstrap-multiselect.css" />
		<link rel="stylesheet" href="../vendor/morris/morris.css" />
		<link rel="stylesheet" href="../vendor/select2/css/select2.css" />
		<link rel="stylesheet" href="../vendor/select2-bootstrap-theme/select2-bootstrap.min.css" />
		<link rel="stylesheet" href="../vendor/datatables/media/css/dataTables.bootstrap5.css" />
		<!-- Theme CSS -->
		<link rel="stylesheet" href="../css/theme.css" />

		<!-- Skin CSS -->
		<link rel="stylesheet" href="../css/skins/default.css" />

		<!-- Theme Custom CSS -->
		<link rel="stylesheet" href="../css/custom.css">

		<!-- Head Libs -->
		<script src="../vendor/modernizr/modernizr.js"></script>

<?php
if (!isset($_SESSION['auth'])) 
	{
		header("Location: ../../login.php?r=2");
	}

$timeout_duration = 30 * 60;
// Vérifier si le timestamp de la dernière activité est défini
if (isset($_SESSION['last_activity'])) {
	$elapsed_time = time() - $_SESSION['last_activity'];
	if ($elapsed_time > $timeout_duration) {
		session_unset();
		session_destroy();
		header("Location: ../../login.php?r=2");
		exit();
	}
}
?>
</head>