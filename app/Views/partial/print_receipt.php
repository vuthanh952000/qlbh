<?php
/**
 * @var string $selected_printer
 * @var bool $print_after_sale
 */
?>
<script type="text/javascript">
function printdoc()
{
	// install firefox addon in order to use this plugin
	if (window.jsPrintSetup)
	{
		// set top margins in millimeters
		jsPrintSetup.setOption('marginTop', '<?php echo config('OSPOS')->settings['print_top_margin'] ?>');
		jsPrintSetup.setOption('marginLeft', '<?php echo config('OSPOS')->settings['print_left_margin'] ?>');
		jsPrintSetup.setOption('marginBottom', '<?php echo config('OSPOS')->settings['print_bottom_margin'] ?>');
		jsPrintSetup.setOption('marginRight', '<?php echo config('OSPOS')->settings['print_right_margin'] ?>');

		<?php if (!config('OSPOS')->settings['print_header'])
		{
		?>
			// set page header
			jsPrintSetup.setOption('headerStrLeft', '');
			jsPrintSetup.setOption('headerStrCenter', '');
			jsPrintSetup.setOption('headerStrRight', '');
		<?php
		}
		if (!config('OSPOS')->settings['print_footer'])
		{
		?>
			// set empty page footer
			jsPrintSetup.setOption('footerStrLeft', '');
			jsPrintSetup.setOption('footerStrCenter', '');
			jsPrintSetup.setOption('footerStrRight', '');
		<?php
		}
		?>

		var printers = jsPrintSetup.getPrintersList().split(',');
		// get right printer here..
		for(var index in printers) {
			var default_ticket_printer = window.localStorage && localStorage['<?php echo esc($selected_printer, 'js') ?>'];
			var selected_printer = printers[index];
			if (selected_printer == default_ticket_printer) {
				// select epson label printer
				jsPrintSetup.setPrinter(selected_printer);
				// clears user preferences always silent print value
				// to enable using 'printSilent' option
				jsPrintSetup.clearSilentPrint();
				<?php if (!config('OSPOS')->settings['print_silently'])
				{
				?>
					// Suppress print dialog (for this context only)
					jsPrintSetup.setOption('printSilent', 1);
				<?php
				}
				?>
				// Do Print
				// When print is submitted it is executed asynchronous and
				// script flow continues after print independently of completetion of print process!
				jsPrintSetup.print();
			}
		}
	}
	else
	{
		window.print();
	}
}

<?php
if($print_after_sale)
{
?>
	$(window).load(function()
	{
		// executes when complete page is fully loaded, including all frames, objects and images
		printdoc();

		// after a delay, return to sales view
		setTimeout(function () {
				window.location.href = "<?php echo site_url('sales') ?>";
			}, <?php echo config('OSPOS')->settings['print_delay_autoreturn'] * 1000 ?>);
	});

<?php
}
?>
</script>