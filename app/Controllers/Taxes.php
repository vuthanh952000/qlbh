<?php

namespace App\Controllers;

use app\Libraries\Tax_lib;
use app\Models\enums\Rounding_mode;
use app\Models\Tax;
use app\Models\Tax_category;
use app\Models\Tax_code;
use app\Models\Tax_jurisdiction;

/**
 * @property tax_lib tax_lib
 * @property rounding_mode rounding_mode
 * @property tax tax
 * @property tax_category tax_category
 * @property tax_code tax_code
 * @property tax_jurisdiction tax_jurisdiction
 */
class Taxes extends Secure_Controller
{
	public function __construct()
	{
		parent::__construct('taxes');

		$this->rounding_mode = model('enums/Rounding_mode');
		$this->tax = model('Tax');
		$this->tax_category = model('Tax_category');
		$this->tax_code = model('Tax_code');
		$this->tax_jurisdiction = model('Tax_jurisdiction');
		
		$this->tax_lib = new Tax_lib();

		helper('tax_helper');
	}

	public function index(): void
	{
		$data['tax_codes'] = $this->tax_code->get_all()->getResultArray();
		if (count($data['tax_codes']) == 0)
		{
			$data['tax_codes'] = $this->tax_code->get_empty_row();
		}

		$data['tax_categories'] = $this->tax_category->get_all()->getResultArray();
		if (count($data['tax_categories']) == 0)
		{
			$data['tax_categories'] = $this->tax_category->get_empty_row();
		}

		$data['tax_jurisdictions'] = $this->tax_jurisdiction->get_all()->getResultArray();
		if (count($data['tax_jurisdictions']) == 0)
		{
			$data['tax_jurisdictions'] = $this->tax_jurisdiction->get_empty_row();
		}

		$data['tax_rate_table_headers'] = get_tax_rates_manage_table_headers();
		$data['tax_categories_table_headers'] = get_tax_categories_table_headers();
		$data['tax_types'] = $this->tax_lib->get_tax_types();

		if(config('OSPOS')->tax_included)
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_INCLUDED;
		}
		else
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_EXCLUDED;
		}

		$data['tax_type_options'] = $this->tax_lib->get_tax_type_options($data['default_tax_type']);

		echo view('taxes/manage', $data);
	}


	/*
	Returns tax_codes table data rows. This will be called with AJAX.
	*/
	public function search(): void
	{
		$search = $this->request->getGet('search', FILTER_SANITIZE_STRING);
		$limit = $this->request->getGet('limit', FILTER_SANITIZE_NUMBER_INT);
		$offset = $this->request->getGet('offset', FILTER_SANITIZE_NUMBER_INT);
		$sort = $this->request->getGet('sort', FILTER_SANITIZE_STRING);
		$order = $this->request->getGet('order', FILTER_SANITIZE_STRING);

		$tax_rates = $this->tax->search($search, $limit, $offset, $sort, $order);

		$total_rows = $this->tax->get_found_rows($search);

		$data_rows = [];
		foreach($tax_rates->getResult() as $tax_rate_row)
		{
			$data_rows[] = get_tax_rates_data_row($tax_rate_row);
		}

		echo json_encode (['total' => $total_rows, 'rows' => $data_rows]);
	}

	/**
	 * Gives search suggestions based on what is being searched for
	 */
	public function suggest_search(): void
	{
		$suggestions = $this->tax->get_search_suggestions($this->request->getPost('term', FILTER_SANITIZE_STRING));	//TODO: There is no get_search_suggestions function in the tax model

		echo json_encode($suggestions);
	}

	/*
	Provides list of tax categories to select from
	*/
	public function suggest_tax_categories(): void
	{
		$suggestions = $this->tax_category->get_tax_category_suggestions($this->request->getPost('term', FILTER_SANITIZE_STRING));

		echo json_encode($suggestions);
	}


	public function get_row(int $row_id): void
	{
		$data_row = get_tax_rates_data_row($this->tax->get_info($row_id));

		echo json_encode($data_row);
	}

	public function view_tax_codes(int $tax_code = -1): void	//TODO: Replace -1 with constant
	{
		$tax_code_info = $this->tax->get_info($tax_code);

		$default_tax_category_id = 1; // Tax category id is always the default tax category	//TODO: Replace 1 with constant
		$default_tax_category = $this->tax->get_tax_category($default_tax_category_id);	//TODO: this variable is never used in the code.

		$tax_rate_info = $this->tax->get_rate_info($tax_code, $default_tax_category_id);

		if(config('OSPOS')->tax_included)
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_INCLUDED;
		}
		else
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_EXCLUDED;
		}

		$data['rounding_options'] = rounding_mode::get_rounding_options();
		$data['html_rounding_options'] = $this->get_html_rounding_options();

		if($tax_code == -1)	//TODO: Replace -1 with constant
		{//TODO: Duplicated code
			$data['tax_code'] = '';
			$data['tax_code_name'] = '';
			$data['tax_code_type'] = '0';
			$data['city'] = '';
			$data['state'] = '';
			$data['tax_rate'] = '0.0000';
			$data['rate_tax_code'] = '';
			$data['rate_tax_category_id'] = 1;
			$data['tax_category'] = '';
			$data['add_tax_category'] = '';
			$data['rounding_code'] = '0';
		}
		else
		{
			$data['tax_code'] = $tax_code;
			$data['tax_code_name'] = $tax_code_info->tax_code_name;
			$data['tax_code_type'] = $tax_code_info->tax_code_type;
			$data['city'] = $tax_code_info->city;
			$data['state'] = $tax_code_info->state;
			$data['rate_tax_code'] = $tax_code_info->rate_tax_code;
			$data['rate_tax_category_id'] = $tax_code_info->rate_tax_category_id;
			$data['tax_category'] = $tax_code_info->tax_category;
			$data['add_tax_category'] = '';
			$data['tax_rate'] = $tax_rate_info->tax_rate;
			$data['rounding_code'] = $tax_rate_info->rounding_code;
		}

		$tax_rates = [];
		foreach($this->tax->get_tax_code_rate_exceptions($tax_code) as $tax_code_rate)	//TODO: get_tax_code_rate_exceptions doesn't exist.  This was deleted by @steveireland in https://github.com/opensourcepos/opensourcepos/commit/32204698379c230f2a6756655f40334308023de9#diff-e746bab6720cf5dbf855de6cda68f7aca9ecea7ddd5a39bb852e9b9047a7a838L435 but it's unclear if that was on purpose or accidental.
		{
			$tax_rate_row = [];
			$tax_rate_row['rate_tax_category_id'] = $tax_code_rate['rate_tax_category_id'];
			$tax_rate_row['tax_category'] = $tax_code_rate['tax_category'];
			$tax_rate_row['tax_rate'] = $tax_code_rate['tax_rate'];
			$tax_rate_row['rounding_code'] = $tax_code_rate['rounding_code'];

			$tax_rates[] = $tax_rate_row;
		}

		$data['tax_rates'] = $tax_rates;

		echo view('taxes/tax_code_form', $data);
	}


	public function view(int $tax_rate_id = -1): void	//TODO: Replace -1 with constant
	{
		$tax_rate_info = $this->tax->get_info($tax_rate_id);

		$data['tax_rate_id'] = $tax_rate_id;
		$data['rounding_options'] = rounding_mode::get_rounding_options();

		$data['tax_code_options'] = $this->tax_lib->get_tax_code_options();
		$data['tax_category_options'] = $this->tax_lib->get_tax_category_options();
		$data['tax_jurisdiction_options'] = $this->tax_lib->get_tax_jurisdiction_options();

		if($tax_rate_id == -1)	//TODO: Replace -1 with constant
		{
			$data['rate_tax_code_id'] = config('OSPOS')->default_tax_code;
			$data['rate_tax_category_id'] = config('OSPOS')->default_tax_category;
			$data['rate_jurisdiction_id'] = config('OSPOS')->default_tax_jurisdiction;
			$data['tax_rounding_code'] = rounding_mode::HALF_UP;
			$data['tax_rate'] = '0.0000';
		}
		else
		{
			$data['rate_tax_code_id'] = $tax_rate_info->rate_tax_code_id;
			$data['rate_tax_code'] = $tax_rate_info->tax_code;
			$data['rate_tax_category_id'] = $tax_rate_info->rate_tax_category_id;
			$data['rate_jurisdiction_id'] = $tax_rate_info->rate_jurisdiction_id;
			$data['tax_rounding_code'] = $tax_rate_info->tax_rounding_code;
			$data['tax_rate'] = $tax_rate_info->tax_rate;
		}

		echo view('taxes/tax_rates_form', $data);
	}

	public function view_tax_categories(int $tax_code = -1): void	//TODO: Replace -1 with constant	//TODO: This appears to be called no where in the code.
	{
		$tax_code_info = $this->tax->get_info($tax_code);	//TODO: Duplicated Code

		$default_tax_category_id = 1; // Tax category id is always the default tax category	//TODO: replace with a constant.
		$default_tax_category = $this->tax->get_tax_category($default_tax_category_id);

		$tax_rate_info = $this->tax->get_rate_info($tax_code, $default_tax_category_id);

		$data['rounding_options'] = rounding_mode::get_rounding_options();
		$data['html_rounding_options'] = $this->get_html_rounding_options();

		if(config('OSPOS')->tax_included)
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_INCLUDED;
		}
		else
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_EXCLUDED;
		}

		if($tax_code == -1)	//TODO: Replace -1 with constant
		{
			$data['tax_code'] = '';
			$data['tax_code_name'] = '';
			$data['tax_code_type'] = '0';
			$data['city'] = '';
			$data['state'] = '';
			$data['tax_rate'] = '0.0000';
			$data['rate_tax_code'] = '';
			$data['rate_tax_category_id'] = 1;
			$data['tax_category'] = '';
			$data['add_tax_category'] = '';
			$data['rounding_code'] = '0';
		}
		else
		{
			$data['tax_code'] = $tax_code;
			$data['tax_code_name'] = $tax_code_info->tax_code_name;
			$data['tax_code_type'] = $tax_code_info->tax_code_type;
			$data['city'] = $tax_code_info->city;
			$data['state'] = $tax_code_info->state;
			$data['rate_tax_code'] = $tax_code_info->rate_tax_code;
			$data['rate_tax_category_id'] = $tax_code_info->rate_tax_category_id;
			$data['tax_category'] = $tax_code_info->tax_category;
			$data['add_tax_category'] = '';
			$data['tax_rate'] = $tax_rate_info->tax_rate;
			$data['rounding_code'] = $tax_rate_info->rounding_code;
		}

		$tax_rates = [];
		foreach($this->tax->get_tax_code_rate_exceptions($tax_code) as $tax_code_rate)	//TODO: get_tax_code_rate_exceptions doesn't exist in the tax model
		{
			$tax_rate_row = [];
			$tax_rate_row['rate_tax_category_id'] = $tax_code_rate['rate_tax_category_id'];
			$tax_rate_row['tax_category'] = $tax_code_rate['tax_category'];
			$tax_rate_row['tax_rate'] = $tax_code_rate['tax_rate'];
			$tax_rate_row['rounding_code'] = $tax_code_rate['rounding_code'];

			$tax_rates[] = $tax_rate_row;
		}

		$data['tax_rates'] = $tax_rates;

		echo view('taxes/tax_category_form', $data);
	}

	public function view_tax_jurisdictions(int $tax_code = -1): void	//TODO: Replace -1 with constant	//TODO: This appears to be called no where in the code.
	{
		$tax_code_info = $this->tax->get_info($tax_code);	//TODO: Duplicated code

		$default_tax_category_id = 1; // Tax category id is always the default tax category
		$default_tax_category = $this->tax->get_tax_category($default_tax_category_id);	//TODO: This variable is not used anywhere in the code

		$tax_rate_info = $this->tax->get_rate_info($tax_code, $default_tax_category_id);

		$data['rounding_options'] = rounding_mode::get_rounding_options();
		$data['html_rounding_options'] = $this->get_html_rounding_options();

		if(config('OSPOS')->tax_included)
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_INCLUDED;
		}
		else
		{
			$data['default_tax_type'] = Tax_lib::TAX_TYPE_EXCLUDED;
		}

		if($tax_code == -1)	//TODO: Replace -1 with constant
		{
			$data['tax_code'] = '';
			$data['tax_code_name'] = '';
			$data['tax_code_type'] = '0';
			$data['city'] = '';
			$data['state'] = '';
			$data['tax_rate'] = '0.0000';
			$data['rate_tax_code'] = '';
			$data['rate_tax_category_id'] = 1;
			$data['tax_category'] = '';
			$data['add_tax_category'] = '';
			$data['rounding_code'] = '0';
		}
		else
		{
			$data['tax_code'] = $tax_code;
			$data['tax_code_name'] = $tax_code_info->tax_code_name;
			$data['tax_code_type'] = $tax_code_info->tax_code_type;
			$data['city'] = $tax_code_info->city;
			$data['state'] = $tax_code_info->state;
			$data['rate_tax_code'] = $tax_code_info->rate_tax_code;
			$data['rate_tax_category_id'] = $tax_code_info->rate_tax_category_id;
			$data['tax_category'] = $tax_code_info->tax_category;
			$data['add_tax_category'] = '';
			$data['tax_rate'] = $tax_rate_info->tax_rate;
			$data['rounding_code'] = $tax_rate_info->rounding_code;
		}

		$tax_rates = [];
		foreach($this->tax->get_tax_code_rate_exceptions($tax_code) as $tax_code_rate)	//TODO: get_tax_code_rate_exceptions doesn't exist in the tax model
		{
			$tax_rate_row = [];
			$tax_rate_row['rate_tax_category_id'] = $tax_code_rate['rate_tax_category_id'];
			$tax_rate_row['tax_category'] = $tax_code_rate['tax_category'];
			$tax_rate_row['tax_rate'] = $tax_code_rate['tax_rate'];
			$tax_rate_row['rounding_code'] = $tax_code_rate['rounding_code'];

			$tax_rates[] = $tax_rate_row;
		}

		$data['tax_rates'] = $tax_rates;

		echo view('taxes/tax_jurisdiction_form', $data);
	}

	public static function get_html_rounding_options(): string
	{
		return rounding_mode::get_html_rounding_options();
	}

	public function save(int $tax_rate_id = -1): void	//TODO: Replace -1 with constant
	{
		$tax_category_id = $this->request->getPost('rate_tax_category_id', FILTER_SANITIZE_NUMBER_INT);
		$tax_rate = parse_tax($this->request->getPost('tax_rate', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));

		if ($tax_rate == 0)	//TODO: Replace 0 with constant?
		{
			$tax_category_info = $this->tax_category->get_info($tax_category_id);	//TODO: this variable is not used anywhere in the code
		}

		$tax_rate_data = [
			'rate_tax_code_id' => $this->request->getPost('rate_tax_code_id', FILTER_SANITIZE_NUMBER_INT),
			'rate_tax_category_id' => $this->request->getPost('rate_tax_category_id', FILTER_SANITIZE_NUMBER_INT),
			'rate_jurisdiction_id' => $this->request->getPost('rate_jurisdiction_id', FILTER_SANITIZE_NUMBER_INT),
			'tax_rate' => $tax_rate,
			'tax_rounding_code' => $this->request->getPost('tax_rounding_code', FILTER_SANITIZE_NUMBER_INT)
		];

		if($this->tax->save_value($tax_rate_data, $tax_rate_id))
		{
			if($tax_rate_id == -1)	//TODO: Replace -1 with constant
			{//TODO: this needs to be replaced with ternary notation
				echo json_encode (['success' => TRUE, 'message' => lang('Taxes.tax_rate_successfully_added')]);
			}
			else //Existing tax_code
			{
				echo json_encode (['success' => TRUE, 'message' => lang('Taxes.tax_rate_successful_updated')]);
			}
		}
		else
		{
			echo json_encode (['success' => FALSE, 'message' => lang('Taxes.tax_rate_error_adding_updating')]);
		}
	}

	public function delete(): void
	{
		$tax_codes_to_delete = $this->request->getPost('ids', FILTER_SANITIZE_NUMBER_INT);

		if($this->tax->delete_list($tax_codes_to_delete))	//TODO: this needs to be replaced with ternary notation
		{
			echo json_encode (['success' => TRUE, 'message' => lang('Taxes.tax_code_successful_deleted')]);
		} else
		{
			echo json_encode (['success' => FALSE, 'message' => lang('Taxes.tax_code_cannot_be_deleted')]);
		}
	}

	/**
	 * Called in the view.
	 * @return void
	 */
	public function suggest_tax_codes(): void
	{
		$suggestions = $this->tax_code->get_tax_codes_search_suggestions($this->request->getPostGet('term', FILTER_SANITIZE_STRING));

		echo json_encode($suggestions);
	}

	/**
	 * Called in the view.
	 * @return void
	 */
	public function save_tax_codes(): void
	{
		$tax_code_id = $this->request->getPost('tax_code_id', FILTER_SANITIZE_NUMBER_INT);
		$tax_code = $this->request->getPost('tax_code', FILTER_SANITIZE_STRING);
		$tax_code_name = $this->request->getPost('tax_code_name', FILTER_SANITIZE_STRING);
		$city = $this->request->getPost('city', FILTER_SANITIZE_STRING);
		$state = $this->request->getPost('state', FILTER_SANITIZE_STRING);

		$array_save = [];	//TODO: the naming of this variable is not good.
		foreach($tax_code_id as $key=>$val)
		{
			$array_save[] = [
				'tax_code_id'=>$val,
				'tax_code'=>$tax_code[$key],
				'tax_code_name'=>$tax_code_name[$key],
				'city'=>$city[$key],
				'state'=>$state[$key]
			];
		}

		$success = $this->tax_code->save_tax_codes($array_save);

		echo json_encode ([
			'success' => $success,
			'message' => lang('Taxes.tax_codes_saved_' . ($success ? '' : 'un') . 'successfully')
		]);
	}

	public function save_tax_jurisdictions(): void
	{
		$jurisdiction_id = $this->request->getPost('jurisdiction_id', FILTER_SANITIZE_NUMBER_INT);
		$jurisdiction_name = $this->request->getPost('jurisdiction_name', FILTER_SANITIZE_STRING);
		$tax_group = $this->request->getPost('tax_group', FILTER_SANITIZE_STRING);
		$tax_type = $this->request->getPost('tax_type', FILTER_SANITIZE_NUMBER_INT);
		$reporting_authority = $this->request->getPost('reporting_authority', FILTER_SANITIZE_STRING);
		$tax_group_sequence = $this->request->getPost('tax_group_sequence', FILTER_SANITIZE_NUMBER_INT);
		$cascade_sequence = $this->request->getPost('cascade_sequence', FILTER_SANITIZE_NUMBER_INT);

		$array_save = [];
		$unique_tax_groups = [];

		foreach($jurisdiction_id as $key => $val)
		{
			$array_save[] = [
				'jurisdiction_id'=>$val,
				'jurisdiction_name'=>$jurisdiction_name[$key],
				'tax_group'=>$tax_group[$key],
				'tax_type'=>$tax_type[$key],
				'reporting_authority'=>$reporting_authority[$key],
				'tax_group_sequence'=>$tax_group_sequence[$key],
				'cascade_sequence'=>$cascade_sequence[$key]
			];

			if (in_array($tax_group[$key], $unique_tax_groups))	//TODO: This can be replaced with `in_array($tax_group[$key], $unique_tax_groups)`
			{
				echo json_encode ([
					'success' => FALSE,
					'message' => lang('Taxes.tax_group_not_unique', ['group' => $tax_group[$key]])
				]);
				return;
			}
			else
			{
				$unique_tax_groups[] = $tax_group[$key];
			}
		}

		$success = $this->tax_jurisdiction->save_jurisdictions($array_save);

		echo json_encode ([
			'success' => $success,
			'message' => lang('Taxes.tax_jurisdictions_saved_' . ($success ? '' : 'un') . 'successfully')
		]);
	}

	public function save_tax_categories(): void
	{
		$tax_category_id = $this->request->getPost('tax_category_id', FILTER_SANITIZE_NUMBER_INT);
		$tax_category = $this->request->getPost('tax_category', FILTER_SANITIZE_STRING);
		$tax_group_sequence = $this->request->getPost('tax_group_sequence', FILTER_SANITIZE_NUMBER_INT);

		$array_save= [];

		foreach($tax_category_id as $key => $val)
		{
			$array_save[] = [
				'tax_category_id'=>$val,
				'tax_category'=>$tax_category[$key],
				'tax_group_sequence'=>$tax_group_sequence[$key]
			];
		}

		$success = $this->tax_category->save_categories($array_save);

		echo json_encode ([
			'success' => $success,
			'message' => lang('Taxes.tax_categories_saved_' . ($success ? '' : 'un') . 'successfully')
		]);
	}

	/**
	 * Called in the view.
	 * @return void
	 */
	public function ajax_tax_codes(): void
	{
		$tax_codes = $this->tax_code->get_all()->getResultArray();

		echo view('partial/tax_codes', ['tax_codes' => $tax_codes]);
	}

	/**
	 * Called in the view.
	 * @return void
	 */
	public function ajax_tax_categories(): void
	{
		$tax_categories = $this->tax_category->get_all()->getResultArray();

		echo view('partial/tax_categories', ['tax_categories' => $tax_categories]);
	}

	/**
	 * Called in the view.
	 * @return void
	 */
	public function ajax_tax_jurisdictions(): void
	{
		$tax_jurisdictions = $this->tax_jurisdiction->get_all()->getResultArray();

		if(config('OSPOS')->tax_included)	//TODO: ternary notation
		{
			$default_tax_type = Tax_lib::TAX_TYPE_INCLUDED;
		}
		else
		{
			$default_tax_type = Tax_lib::TAX_TYPE_EXCLUDED;
		}

		$tax_types = $this->tax_lib->get_tax_types();

		echo view('partial/tax_jurisdictions', [
			'tax_jurisdictions' => $tax_jurisdictions,
			'tax_types' => $tax_types,
			'default_tax_type' => $default_tax_type
		]);
	}
}