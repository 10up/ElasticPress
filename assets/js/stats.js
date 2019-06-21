import Chart from 'chartjs';
import { epChartData } from 'window';

/**
 * Generates a random string representing a color.
 *
 * @returns {string|string}
 */
function getRandomColor() {
	let letters = '0123456789ABCDEF';
	let color   = '#';
	for ( let i = 0; i < 6; i++ ) {
		color += letters[ Math.floor( Math.random() * 16 ) ];
	}
	return color;
}

const barData   = Object.entries( epChartData.indices_data );
const barLabels = [];
const barDocs   = [];
const barColors = [];

Chart.defaults.global.legend.labels.usePointStyle = true;

barData.forEach( function( data ) {
	barLabels.push( data[1].name );
	barDocs.push( data[1].docs  );
	barColors.push( getRandomColor() );
} );

let documentChart = new Chart( document.getElementById( 'documentChart' ), {
	type: 'horizontalBar',
	data: {
		labels: barLabels,
		datasets: [
			{
				label: 'Documents',
				backgroundColor: barColors,
				data: barDocs
			}
		]
	},
	options: {
		legend: {
			display: false
		},
		title: {
			display: true,
		}
	}
} );

let queriesTotalChart = new Chart( document.getElementById( 'queriesTotalChart' ), {
	type: 'pie',
	data: {
		labels: [ 'Indexing operations', 'Total Query operations' ],
		datasets: [{
			label: '',
			backgroundColor: [ '#5ba9a7', '#2e7875','#a980a4' ],
			data: [ epChartData.index_total, epChartData.query_total ]
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
					let dataset      = data.datasets[item.datasetIndex];
					let currentValue = dataset.data[item.index];

					return 'Operations: ' + currentValue ;
				}
			}
		}
	}
} );

let queriesTimeChart = new Chart( document.getElementById( 'queriesTimeChart' ), {
	type: 'pie',
	data: {
		labels: [ 'Avg indexing time in ms', 'Avg query time in ms' ],
		datasets: [{
			label: '',
			backgroundColor: [ '#9ea6c7', '#93b3d5' ],
			data: [ epChartData.index_time_in_millis, epChartData.query_time_in_millis ]
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
					let dataset = data.datasets[item.datasetIndex];
					let currentValue = dataset.data[item.index];

					return + currentValue + ' milliseconds';
				}
			}
		}
	}
} );
