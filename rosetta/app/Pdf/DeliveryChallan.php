<?php
namespace Rosetta\Pdf;

use Rosetta\Models\Address;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\PurchaseOrder\PurchaseLine;
use TCPDF;

class DeliveryChallan extends TCPDF
{
    public function header()
    {

    }
    public function footer()
    {

    }
    public function generate(BlanketOrder $blanketOrder, Company $company, Address $shippingAddress, $lines)
    {   
        $this->AddPage();
        /*
        $this->SetX(4);
        $this->Cell(130,12,"THE Right floor trading LLC",0,1,'C');
        $this->SetFont('dejavusans','N',8);
        $this->Cell(130,6,'B4/402, Kumar Princetown Royal',0,1,'C');
        $this->Cell(130,6,'near palace orchard',0,1,'C');
        $this->Cell(130,6,'Contact',0,1,'C');
        $this->Cell(130,6,'Email | website',0,1,'C');
        */
        $this->SetFont('freesans','B',13);
        $this->Cell(130,12,$company->name,0,1,'L');
        $this->SetFont('dejavusans','N',8);
        $companyAddress = $company->address . ', ' . $company->address_2;
        $this->Cell(130,5,$companyAddress,0,1,'L');  
        $companyAddress2 = $company->city . ', ' . $company->state . ', ' . $company->post_code; 
        $this->Cell(130,5,$companyAddress2,0,1,'L');
        $contact = 'Contact: ' . $company->phone_no;
        $this->Cell(130,5,$contact,0,1,'L');
        $temp = $company->email . ' | ' . $company->website;
        $this->Cell(130,5,$temp,0,1,'L');

        $this->SetFont('dejavusans','B',11);
        $this->Cell(190,12,"Delivery Challan/Note",0,1,'C');
        $this->Image(public_path() . "/images/rosetta.png",160,20,36,17);

        $this->SetFillColor(239,229,152);
        $this->SetFont('dejavusans','B',8);
        $this->Cell(100,5,"To,",0,0,'L',1);
        $this->Cell(50,5,"VOUCHER NO",0,0,'L',1);
        $this->Cell(40,5,"Delivery Date",0,1,'R',1);

        $this->SetFont('dejavusans','N',8);
        $this->Cell(100,5,$blanketOrder->orderable->name,0,0,'L',0);
        $this->Cell(50,5,$blanketOrder->order_number,0,0,'L',0);
        $date = date('d-m-y');
        $this->Cell(40,5,$date,0,1,'R',0);
        $address = $shippingAddress->address . ', ' . $shippingAddress->address_2;
        $this->Cell(100,5,$address,0,1,'L');
        $address2 = $shippingAddress->city . ', ' . $shippingAddress->state . ', ' . $shippingAddress->post_code; 
        $this->Cell(100,5,$address2,0,1,'L');

        $this->SetLineStyle(array('width' => 0.1, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(192, 192, 192)));


        $this->Ln();
        $this->SetFont('dejavusans','B',8);
        $this->Cell(20,5,"SR NO",1,0,'L',1);
        $this->Cell(100,5,"PRODUCTS DESCRIPTION",1,0,'C',1);
        $this->Cell(50,5,"QTY",1,0,'C',1);
        $this->Cell(20,5,"UOM",1,1,'C',1);
        $this->SetFont('dejavusans','N',8);

        $cnt = 1;
        $qty = 0;
        foreach($lines as $line)
        {
            $this->Cell(20,5,$cnt,1,0,'L');
            $product_desc = $line->product->category_code . ' - ' . $line->product->collection_code . ' - ' . $line->product->colour_code;
            $this->Cell(100,5,$product_desc,1,0,'C');
            $this->Cell(50,5,$line->shipped_quantity,1,0,'C');

            $bom = $line->product->base_unit_of_measure;

            if($company->region)
            {
                $uom = $company->region->getUOM('sales_unit_of_measure',$bom);
                if($uom)
                {
                    $bom = $uom->to;
                }
            }//#773

            $this->Cell(20,5,$bom,1,1,'C');
          //  $this->Cell(20,5,$line->product->sales_unit_of_measure,1,1,'C');
            $cnt += 1;
            $qty += $line->shipped_quantity;
            $line->dc_printed = "1";
            $line->save();
        }
        $this->SetFont('dejavusans','B',8);

        $this->Cell(120,5,"TOTAL",1,0,'R');
        $this->Cell(50,5,$qty,1,0,'C');
        $this->Cell(20,5,"",1,1,'C');
        $this->Cell(190,5,"Thank you for your business !",1,0,'C');
        
        return $this;
    }
}