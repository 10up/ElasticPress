/* eslint-disable no-new */

import Chart from 'chart.js';

const { epChartData } = window;

/**
 * Generates a random string representing a color.
 *
 * @return {string} Random color
 */
function getRandomColor() {
	const letters = '0123456789ABCDEF';
	let color = '#';

	for (let i = 0; i < 6; i += 1) {
		color += letters[Math.floor(Math.random() * 16)];
	}

	return color;
}

const barData = Object.entries(epChartData.indices_data);
const barLabels = [];
const barDocs = [];
const barColors = [];

Chart.defaults.global.legend.labels.usePointStyle = true;

barData.forEach(function (data) {
	barLabels.push(data[1].name);
	barDocs.push(data[1].docs);
	barColors.push(getRandomColor());
});

new Chart(document.getElementById('documentChart'), {
	type: 'horizontalBar',
	data: {
		labels: barLabels,
		datasets: [
			{
				label: 'Documents',
				backgroundColor: barColors,
				data: barDocs,
			},
		],
	},
	options: {
		legend: {
			display: false,
		},
		title: {
			display: true,
		},
	},
});

new Chart(document.getElementById('queriesTotalChart'), {
	type: 'pie',
	data: {
		labels: ['Indexing operations', 'Total Query operations'],
		datasets: [
			{
				label: '',
				backgroundColor: ['#5ba9a7', '#2e7875', '#a980a4'],
				data: [epChartData.index_total, epChartData.query_total],
			},
		],
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
				 * @param {Object} item Chat item
				 * @param {Object} data Data
				 * @return {string} Operations
				 */
				label(item, data) {
					const dataset = data.datasets[item.datasetIndex];
					const currentValue = dataset.data[item.index];

					return `Operations: ${currentValue}`;
				},
			},
		},
	},
});

new Chart(document.getElementById('queriesTimeChart'), {
	type: 'pie',
	data: {
		labels: ['Avg indexing time in ms', 'Avg query time in ms'],
		datasets: [
			{
				label: '',
				backgroundColor: ['#9ea6c7', '#93b3d5'],
				data: [epChartData.index_time_in_millis, epChartData.query_time_in_millis],
			},
		],
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
				 * @param {Object} item Tooltip item
				 * @param {Object} data Tooltip data
				 * @return {string} current value in milliseconds
				 */
				label(item, data) {
					const dataset = data.datasets[item.datasetIndex];
					const currentValue = dataset.data[item.index];

					return `${+currentValue} milliseconds`;
				},
			},
		},
	},
});
