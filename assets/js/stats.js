Chart.defaults.global.legend.labels.usePointStyle = true;
var queriesTotalChart = new Chart( document.getElementById( 'queriesTotalChart' ), {
	type: 'pie',
	data: {
		labels: ['Indexing operations', 'Total Query operations', 'Total Autosuggest operations'],
		datasets: [{
			label: '',
			backgroundColor: ['#5ba9a7', '#2e7875','#a980a4'],
			data: [epChartData.index_total, epChartData.query_total, epChartData.suggest_total]
		}]
	},

	options: {
		responsive: false,
		title: {
			display: true,
		},
		legend: {
			position: 'right',
		},
		tooltips: {
			callbacks: {
				/**
				 * Appends the string operations before tooltip value
				 *
				 * @param item
				 * @param data
				 * @returns {string}
				 */
				label: function( item, data ) {
					var dataset = data.datasets[item.datasetIndex];
					var currentValue = dataset.data[item.index];

					return 'Operations: ' + currentValue ;
				}
			}
		}
	}
} );

var queriesTimeChart = new Chart( document.getElementById( 'queriesTimeChart' ), {
	type: 'pie',
	data: {
		labels: ['Avg indexing time in ms', 'Avg query time in ms', 'Avg autosuggest time in ms'],
		datasets: [{
			label: '',
			backgroundColor: ['#9ea6c7', '#93b3d5', '#9bdcd9'],
			data: [epChartData.index_time_in_millis, epChartData.query_time_in_millis, epChartData.suggest_time_in_millis]
		}]
	},

	options: {
		responsive: false,
		title: {
			display: true,
		},
		legend: {
			position: 'right',
		},
		tooltips: {
			callbacks: {
				/**
				 * Appends the string milliseconds after tooltip value
				 *
				 * @param item
				 * @param data
				 * @returns {string}
				 */
				label: function( item, data ) {
					var dataset = data.datasets[item.datasetIndex];
					var currentValue = dataset.data[item.index];

					return + currentValue + ' milliseconds';
				}
			}
		}
	}
} );

var barData  = Object.entries( epChartData.indices_data  );
var labels   = [];
var docs     = [];

barData.forEach( function( data ) {
	labels.push( data[1].name );
} );

barData.forEach( function( data ) {
	docs.push( data[1].docs  );
} );
var documentChart = new Chart( document.getElementById( 'documentChart' ), {
	type: 'horizontalBar',
	data: {
		labels: labels,
		datasets: [
			{
				label: 'Documents',
				backgroundColor: ['#bb5e83', '#2e7875'],
				data: docs
			}
		]
	},
	options: {
		legend: { display: false },
		title: {
			display: true,
		}
	}
} );

