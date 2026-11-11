<?php

namespace Rosetta\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Predis\Connection\ConnectionException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Mail\CompanyDealersCreated;
use Rosetta\Mail\DealerCreated;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\User;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;
use Rosetta\Services\NAV\CustomerServiceInterface;
use Rosetta\Services\Users\UserServiceInterface;

class LoadDealersUnderCompany extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'load:dealersUnderCompany {filename} {companyId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This will load Dealers under Company.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $customerService, $userService, $activeProductAttributesService;
    public function __construct(CustomerServiceInterface $customerService, UserServiceInterface $userService, ActiveProductAttributesServiceInterface $activeProductAttributesService)
    {
        $this->customerService = $customerService;
        $this->userService = $userService;
        $this->activeProductAttributesService = $activeProductAttributesService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $filename = $this->argument('filename');
        $companyId = $this->argument('companyId');
        $spreadsheet = IOFactory::load($filename);
        if(!file_exists($filename))
        {
            echo "file $file does not exist\n";
            exit(0);
        }
        $worksheet = $spreadsheet->getActiveSheet();
        $maxRow = $worksheet->getHighestDataRow();
        $company = Company::find($companyId);
        $user = User::where('email', '=', 'superadmin@rosettaproducts.com')->first();
      //  $dealers = [];
        $distributor = null;
        $toEmailIds = [];
        $sender = $user;
        $brand = config('brand.brand_short');

        $companyAdminQuery = User::where('company_id','=',$company->id);
        $companyAdminQuery->whereHas('roles',function($q) {
                $q->where('name','=','companyAdmin');
            });
        $companyAdmins = $companyAdminQuery->get();

        foreach ($companyAdmins as $user) {
            if($user->communication_email)
                $toEmailIds[] = $user->communication_email;
        }//add Company Admin Email Ids

        $superAdminEmailQuery = User::where('distributor_id','=',null)
                    ->where('dealer_id','=',null)
                    ->where('company_id','=',null);
        $superAdminEmailQuery->whereHas('roles', function($q){
                $q->where('name', '=', 'superadmin');
            });
        $superAdmins = $superAdminEmailQuery->get();
        foreach ($superAdmins as $user) {
            if($user->communication_email)
                $toEmailIds[] = $user->communication_email;
        }//add Super Admin Email Ids

        $queuePrefix = config('brand.queue_prefix');
        $queue = $queuePrefix.'.emails';

        $UserList = [];
        for($i=2;$i<=$maxRow;$i++)
        {
            echo "----- Processing row $i ----- \n";
            try
            {
                if($worksheet->getCell('A'.$i)->getValue())
                {
                    $dealerExists = Dealer::where('name', '=', $worksheet->getCell('A'.$i)->getValue())
                                            ->where('company_id', '=', $company->id)
                                            ->first();
                    if(!$dealerExists)
                    {
                        $email = $worksheet->getCell('S'.$i)->getValue();
                        $userEmailExist = false;
                        if($email)
                        {
                            $userEmailExist = User::where('email', '=', $email)->first();
                        }
                        if($userEmailExist)//Email already in use
                        {
                            echo "User Email already in use, check row ". $i ."\n";
                        }
                        else
                        {
                            $userPhoneExist = false;
                            $phone = $worksheet->getCell('T'.$i)->getValue();
                            if($phone)
                            {
                                $userPhoneExist = User::where('phone', '=', $phone)->first();
                            }
                            if($userPhoneExist)
                            {
                                echo "User Phone already in use, check row ". $i ."\n";
                            }
                            else 
                            {
                                $params['name'] = $worksheet->getCell('A'.$i)->getValue();
                                $params['contact'] = $worksheet->getCell('W'.$i)->getValue();
                                $params['email'] = $worksheet->getCell('S'.$i)->getValue();
                                $params['phone'] = $worksheet->getCell('T'.$i)->getValue();
                                $params['fax'] =  $worksheet->getCell('U'.$i)->getValue();
                                $params['zipcode'] = $worksheet->getCell('F'.$i)->getValue();
                                $params['state'] = $worksheet->getCell('D'.$i)->getValue();//can not be null
                                $params['city'] = $worksheet->getCell('E'.$i)->getValue();
                                $params['address'] = substr($worksheet->getCell('B'.$i)->getValue(), 0, 50);
                                $params['address2'] = substr($worksheet->getCell('B'.$i)->getValue(), 50, 50);
                               // $params['territory'] = $navDistributor->getTerritory_Code();
                                $params['billingLocationCode'] = $worksheet->getCell('X'.$i)->getValue();
                                $params['generalBusinessPostingGroup'] = "NATIONAL";
                                $params['paymentApplicationMethod'] = "Manual";
                                $params['paymentMethod'] = "AS AGREED";
                                $params['paymentTerms'] = $worksheet->getCell('G'.$i)->getValue();
                                $params['cashFlowPaymentTerms'] = '';
                                $params['shippingReserve'] = "Optional";
                                $params['shippingAdvice'] = "Complete";
                                $params['shippingMethod'] = "AS AGREED";
                                $params['copySellFrom'] = "Company";
                                if($worksheet->getCell('L'.$i)->getValue() != null)
                                {
                                    $params["structure"] = "GST SERVIC";
                                    $params['tax_information']['gst']['number'] = $worksheet->getCell('L'.$i)->getValue();
                                    $params['tax_information']['gst']['registration_type'] = "GSTIN";
                                    $params['tax_information']['gst']['customer_type'] = $worksheet->getCell('K'.$i)->getValue();
                                }
                                if($worksheet->getCell('P'.$i)->getValue() != null)
                                {
                                    $params["structure"] = "VAT(EX)";   
                                    $params['tax_information']['vat']['tin'] = $worksheet->getCell('P'.$i)->getValue();
                                    $params['tax_information']['vat']['export'] = $worksheet->getCell('Q'.$i)->getValue();
                                    $params['tax_information']['vat']['exempted'] = $worksheet->getCell('R'.$i)->getValue();
                                }
                                $params['tax_information']['tax']['lst'] = $worksheet->getCell('I'.$i)->getValue();
                                $params['tax_information']['tax']['cst'] = $worksheet->getCell('J'.$i)->getValue();
                                
                                $params['tax_information']['pan']['number'] = $worksheet->getCell('N'.$i)->getValue();
                                if($worksheet->getCell('M'.$i)->getValue() != null)
                                {
                                    $status = [
                                        'Pan Applied' => 'PANAPPLIED',
                                        'Pan Invalid' => 'PANINVALID',
                                        'Pan Not Available' => 'PANNOTAVBL'
                                    ];
                                    $params['tax_information']['pan']['status'] = $status[$worksheet->getCell('M'.$i)->getValue()];
                                }
                                else
                                    $params['tax_information']['pan']['status'] = null;
                                $params['tax_information']['pan']['reference_number'] = $worksheet->getCell('O'.$i)->getValue();
                                if($worksheet->getCell('H'.$i)->getValue() != null)
                                    $params['credit_limit'] = $worksheet->getCell('H'.$i)->getValue();//can not be null
                                else
                                    $params['credit_limit'] = 0;
                                $navDealer = $this->customerService->createDealer($company, $params, $distributor);
                                $dealer = new Dealer;
                                $dealer->nav_id = $navDealer->getNo();
                                $dealer->name = $navDealer->getName();
                                $dealer->credit_limit = $params['credit_limit'];
                                if($params['paymentTerms'] != null)
                                {
                                    $days = [
                                        '7 DAYS' => 7,
                                        '15 DAYS' => 15,
                                        '1 MONTH' => 30,
                                        '1.5 MONTH' => 45,
                                        '100% ADVC' => 0
                                    ];
                                    $dealer->overdue_hold_days = $days[$params['paymentTerms']];
                                }
                                else
                                    $dealer->overdue_hold_days = 0;
                                if($worksheet->getCell('V'.$i)->getValue() != null)
                                    $dealer->overdue_balance = $worksheet->getCell('V'.$i)->getValue();// can not be null
                                else
                                    $dealer->overdue_balance = 0;
                                $dealer->post_code = $params['zipcode'];
                                $dealer->state_code = $params['state'];
                                $dealer->city_code = $params['city'];
                                $dealer->company()->associate($company);
                                $dealer->author()->associate($user);
                                $dealer->save();
                                $dealerUserEmail = $worksheet->getCell('S'.$i)->getValue();
                                if($worksheet->getCell('S'.$i)->getValue() != null || $worksheet->getCell('T'.$i)->getValue() != null)
                                {
                                    $request['user']['email'] = $dealerUserEmail;
                                    $password = Str::random(8);
                                    $request['user']['password'] = $password;
                                    $request['user']['phone'] = $worksheet->getCell('T'.$i)->getValue();
                                    $this->userService->create($user, $request['user'], 'dealer', $dealer);
                                    //set credentials to send email to admins
                                    $UserList[] = ['email' => $dealerUserEmail,
                                            'password' => $password,
                                            'name' => $dealer->name,
                                            'phone' => $worksheet->getCell('T'.$i)->getValue()
                                            ];
                                }
                                 $this->activeProductAttributesService->load($company->id, 'dealer', $dealer->id);
                                //commented as per discussion with Adnan, as Dealers will be activated in phases and not during loads
                           /*     $Qmessage = (new DealerCreated($dealer, $distributor,$dealerUserEmail, $password))->onQueue($queue);
                                Mail::to($dealerUserEmail)->queue($Qmessage);*/
                            }   
                        }
                    }   
                    else//Record exists
                    {
                        echo "Dealer already exists - ".$dealerExists->name ."\n";
                    }                   
                }
                else//Name is null
                {
                    echo "Name required, check row ". $i. "\n";
                }
            }
            catch(\Exception $e)
            {
                echo "Error ". $e ." processing record with Name - " .$worksheet->getCell('A'.$i)->getValue(). "\n";
            } 
        }
        if(count($UserList))
        {
            $Qmessage_Admins = (new CompanyDealersCreated($company,$UserList))->onQueue($queue);
            try
            {
                if(!empty($toEmailIds))
                    Mail::to($toEmailIds)->queue($Qmessage_Admins);
            }
            catch(ConnectionException $e)
            {
                throw new RedisException;
            }
        }
    }
}
