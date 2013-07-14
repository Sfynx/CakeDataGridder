<?php
/**
 * DataGrid helper
 */
class DataGridHelper extends AppHelper {

	public $helpers = array('Html','Paginator','ImageCropResize.Image');

	private $__columns = array();

	private $__actions = array();

	private $__filters = array();

	private $__elementsDir = 'datagrid';

	private $__pluginName = null;

	private $__defaults = array(
		'ajax' => true,
		'update' => '#content',
		'column' => array(
			'sort'				=> false,
			'type'				=> 'string',
			'htmlAttributes'	=> false,
			'iconClass'			=> 'icon'
		),
		'grid' => array(
			'class' => 'data_grid'
		),
		'pagination' => array(
			'numbers' => array()
		),
		'filter' => array()
	);

	public function __construct(View $View, $settings = array()) {
		parent::__construct($View, $settings);

		$this->__defaults = array_merge($this->__defaults, $settings);

		$explode = explode('/',realpath(__DIR__ . DS . '..' . DS . '..'));
		$this->__pluginName = end($explode);
	}

	public function addColumn($label, $valuePath, array $options = array()) {
		$options = array_merge($this->__defaults['column'], $options);

		$slug = Inflector::slug($label);

		$this->__columns[$slug] = array(
			'label' => $label,
			'value_path' => $valuePath,
			'options' => $options
		);

		return $slug;
	}

	public function addFilter($fieldName, array $options = array()) {
		$options = array_merge($this->__defaults['filter'], $options);

		$this->__filters[$fieldName] = array(
			'fieldName' => $fieldName,
			'options' => $options
		);

		return $fieldName;
	}

	public function addAction($name, array $url, array $trailingParams = array(), array $options = array(), $confirmMessage = false) {
		//Check if we already have an actions column
		if (!$this->__hasActionsColumn()) {
			$this->addColumn(__('Actions'), null, array('type' => 'actions'));
		}

		$slug = Inflector::slug($name);

		$this->__actions[$slug] = array(
			'name' => $name,
			'url' => $url,
			'trailingParams' => $trailingParams,
			'options' => $options,
			'confirmMessage' => $confirmMessage
		);

		return $slug;
	}

	private function __hasActionsColumn() {
		foreach ($this->__columns as $column) {
			if ($column['options']['type'] == 'actions') {
				return true;
			}
		}

		return false;
	}

	public function header() {
		$columns = $this->__columns;

		foreach ($this->__columns as $key => $column) {
			if ($column['options']['htmlAttributes']) {
				$columns[$key]['options']['htmlAttributes'] = $this->_parseAttributes($column['options']['htmlAttributes']);
			}
		}

		return $this->_View->element($this->__elementsDir . DS . 'headers', array(
			'headers' => $columns
		),
		array(
			'plugin' => $this->__pluginName
		));
	}

	public function rows($dataRows) {
		$rows = array();
		foreach ($dataRows as $row) {
			$row = $this->row($row);

			$rows[] = $row;
		}

		return implode("\n", $rows);
	}

	public function row($data) {
		$rowData = array();
		foreach ($this->__columns as $column) {
			$rowData[] = array(
				'text' => $this->__generateColumnData($data, $column),
				'htmlAttributes' => $this->_parseAttributes($column['options']['htmlAttributes'])
			);
		}

		return $this->_View->element($this->__pluginName . '.' . $this->__elementsDir . DS . 'row', array(
			'rowData' => $rowData
		));
	}

	public function filter() {
		return $this->_View->element($this->__pluginName . '.' . $this->__elementsDir . DS . 'filter', array(
			'filters' => $this->__filters
		));
	}

	private function __generateColumnData($data, $column) {
		switch($column['options']['type']) {
			case 'switcher':
				$value = intval(Set::extract($column['value_path'], $data));
				$link = isset($column['options']['url']) ? $column['options']['url'] : '#';
				$icon = isset($column['options']['icon']) ? ' ' . $column['options']['iconClass'] . ' ' . $column['options']['icon'] : '';

				$class = $value == 1 ? 'enabled' : 'disabled';

				$trailingParams = array();
				if (!empty($column['options']['trailingParams'])) {
					foreach ($column['options']['trailingParams'] as $key => $param) {
						$trailingParams[$key] = Set::extract($param, $data);
					}
				}

				if (is_array($link)) {
					$link += $trailingParams;
				}

				return $this->Html->link($value, $link, array('class' => 'switcher ' . $class . $icon));
				break;
			case 'actions':
				$actions = array();
				foreach ($this->__actions as $action) {
					$trailingParams = array();
					if (!empty($action['trailingParams'])) {
						foreach ($action['trailingParams'] as $key => $param) {
							$trailingParams[$key] = Set::extract($param, $data);
						}
					}

					if ($action['confirmMessage']) {
						preg_match_all('/{(.*?)}/', $action['confirmMessage'], $confirmVariables);

						foreach ($confirmVariables[1] as $key => $valuePath) {
							$action['confirmMessage'] = str_replace($confirmVariables[0][$key], Set::extract($valuePath, $data), $action['confirmMessage']);
						}
					}
					$actions[] = array(
						'name' => $action['name'],
						'url' => Router::url($action['url'] + $trailingParams),
						'options' => $action['options'],
						'confirmMessage' => $action['confirmMessage']
					);
				}

				return $this->_View->element($this->__pluginName . '.' . $this->__elementsDir . DS . 'actions_column', array(
					'actions' => $actions
				));

				break;
			case 'image':
				$value = Set::extract($column['value_path'], $data);

				if (isset($column['options']['resize']) && $column['options']['resize']) {
					$image = $this->Image->resize($value, $column['options']['resize']);
				} else {
					$image = $this->Html->image($value, $column['options']);
				}

				if (isset($column['url'])) {
					$image = $this->Html->link($image, $column['url'], array('escape' => false));
				}

				return $image;
				break;
			case 'string':
			default:
				return Set::extract($column['value_path'], $data);
		}
	}

	public function generate($data, array $options = array()) {
		$header = $this->header();
		$rows = $this->rows($data);
		$pagination = $this->pagination();
		$filter = $this->filter();

		$options = array_merge($this->__defaults['grid'], $options);

		if (!isset($options['id'])) {
			$options['id'] = 'DataGrid';
		}

		if ($this->__defaults['ajax']) {
			$this->__addAjaxSort($options);
			$this->__addAjaxPagination($options);
			$this->__addAjaxSwitcher($options);
			$this->__addAjaxFilter($options);
		}

		return $this->_View->element($this->__pluginName . '.' . $this->__elementsDir . DS . 'grid', array(
			'header' => $header,
			'rows' => $rows,
			'pagination' => $pagination,
			'filter' => $filter,
			'options' => $this->_parseAttributes($options)
		));
	}

	private function __addAjaxSort(array $gridOptions) {
		$selector = '#' . $gridOptions['id'];
		$this->Html->scriptBlock(<<<AJAXSORT
			$('body').on('click', '{$selector} .sort', function(ev) {
				ev.preventDefault();

				$.get($(this).attr('href'), function(data) {
					$('{$this->__defaults['update']}').html(data);
				});
			});
AJAXSORT
		, array('inline' => false));
	}

	private function __addAjaxPagination(array $gridOptions) {
		$selector = '#' . $gridOptions['id'];
		$this->Html->scriptBlock(<<<AJAXSORT
			$('body').on('click', '{$selector} .pagination a', function(ev) {
				ev.preventDefault();

				$.get($(this).attr('href'), function(data) {
					$('{$this->__defaults['update']}').html(data);
				});
			});
AJAXSORT
		, array('inline' => false));
	}

	private function __addAjaxSwitcher(array $gridOptions) {
		$selector = '#' . $gridOptions['id'];

		$this->Html->scriptBlock(<<<AJAXSORT
			var switcher = function(el) {
				if(el.hasClass('disabled')) {
					el.removeClass('disabled');
					el.text(1);
				}
				else {
					el.addClass('disabled');
					el.text(0);
				}
			};

			$('body').on('click', '{$selector} .switcher', function(ev) {
				ev.preventDefault();

				if($(this).attr('href') && $(this).attr('href') != '#') {
					$.post($(this).attr('href'), $.proxy(function() {
						switcher($(this));
					},this));
				}
				else {
					switcher($(this));
				}
			});
AJAXSORT
		, array('inline' => false));
	}

	private function __addAjaxFilter(array $gridOptions) {
		$selector = '#' . $gridOptions['id'];

		$this->Html->scriptBlock(<<<AJAXSORT
			$('body').on('submit', '{$selector} .filter_form', function(ev) {
				ev.preventDefault();

				var action = $(this).attr('action');
				var search = $(this).find('.searchFormGrid').val();

				var data = $(this).serialize();

				$.post(action, data, function(html){
					$('{$this->__defaults['update']}').html(html);
				});
			});
AJAXSORT
		, array('inline' => false));
	}

	public function pagination(array $options = array()) {
		$options = array_merge($this->__defaults['pagination'], $options);

		return $this->Paginator->numbers($options['numbers']);
	}

	public function reset() {
		$this->__columns = array();
		$this->__actions = array();
	}

	public function defaults($options) {
		$this->__defaults = array_merge($this->__defaults, $options);
	}
}