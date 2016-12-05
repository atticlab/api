<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\Invoices;
use App\Models\InvoicesStatistic;

class TestController extends ControllerBase
{
    public function testAction()
    {
        $count_expired = 0; 
        $count_used = 0; 
        $count_all = 0;
        
        //получаем все инвойсы
        try {
            $invoices = Invoices::find();            
        } catch (Exeption $e) {
            $this->logger->error("Invoices finding error: " . (string)$message);
            return false;
        }
        
        //echo '<pre>' . print_r($invoices, 1) . '</pre>';exit;
        
        //если инвойсы есть стартуем бот
        if (count($invoices) > 0) {
            $this->logger->info("Bot started");
            
            $statistic = array();
            
            foreach ($invoices as $invoice_code) {
                $invoice = Invoices::findFirst($invoice_code->id);
                
                $created_time = $invoice_code->created - $invoice_code->created % 86400;               
                $statistic[$created_time]['invoices'][] = $invoice_code;
                $statistic[$created_time]['all']++;
                
                //просроченные инвойсы
                if (is_numeric($invoice_code->requested) ) {
                    $statistic[$created_time]['used']++;
                //использованные инвойсы 
                } elseif ( time() > $invoice_code->expires ) {                    
                    $statistic[$created_time]['expired']++;
                }
                
                $invoice->is_in_statistic = true;
                $invoice->update();
                
            }
            
            foreach ($statistic as $time => $item) {
                //create statistic                
                $count_expired = ($item['expired']) ? $item['expired'] : 0;
                $count_used = ($item['used']) ? $item['used'] : 0;
                $count_all = ($item['all']) ? $item['all'] : 0;
                
                $this->createStatistic($count_expired, $count_used, $count_all, $time);
               
            }
            
            //удаляем все инвойсы с is_in_statistic = 1
            $this->removeInvoiceInStatistic($invoices);
    
            $this->logger->info("Statistics bot finished");
        }
       
//        try {
//            $inv = InvoicesStatistic::find($date);
//            echo '<pre>' . print_r($inv, 1) . '</pre>';
//        } catch (Exeption $e) {
//            echo 'false';
//        }
    }
    
    private function createStatistic($expired, $used, $all, $date) 
    {       
        //находим статистку по дате
        //если есть то обновляем
        if (InvoicesStatistic::isExist($date)){
         
            $inv_statistic = InvoicesStatistic::findFirst($date);
            $inv_statistic->expired += $expired;
            $inv_statistic->used += $used;
            $inv_statistic->all += $all;
            try {
                $inv_statistic->update();
                $this->logger->info("Statistic on date created");
            } catch (Exception $e) {
                $this->logger->error('Failed to update invoices statistic -> ' . $e->getMessage());
            }    
        //если статистики нет то создаем    
        } else {
            
            $inv_statistic = new InvoicesStatistic($date);           
            $inv_statistic->expired = $expired;
            $inv_statistic->used = $used;
            $inv_statistic->all = $all;

            try {
                $inv_statistic->create();
                $this->logger->info("Statistic on date created");
            } catch (Exception $e) {
                $this->logger->error('There is an error of saving Statistic.' . $e->getMessage());
            }           
        }
    }
    
    
    public function removeInvoiceInStatistic($invoices)
    {
        foreach ($invoices as $item) {
            if ($item->is_in_statistic) {                
                try {        
                    $obj = Invoices::findFirst($item->id);
                    $obj->delete();
                    $this->logger->error("Invoices in statistic delete");
                } catch (Exeption $e) {
                    $this->logger->error("Invoices in statistic delete error: " . (string)$message);                    
                }
            }
        }
    } 
    
    
    
    public function createAction()
    {        
        
//        $expires = time() - $this->config->invoice->expired;        
//        $this->createInvoice($expires, time());
//       
//        $expires1 = 1380865499 - $this->config->invoice->expired;
//        $this->createInvoice($expires1, 1380865499);
        
        
//        
//        $expires2 = time() - $this->config->invoice->expired - (3600 * 2);
//        $this->createInvoice($expires2, time());
//        
//        $expires3 = time() + $this->config->invoice->expired;
//        $this->createInvoice($expires3, time(), time() * 1);
//        
//        $expires3 = time() - $this->config->invoice->expired - (3600 * 3);
//        $this->createInvoice($expires3, time());
//        
//        $expires4 = time() - $this->config->invoice->expired - (3600 * 4);
//        $this->createInvoice($expires4, time() - 60*60*24*1);
//        
//        $expires5 = time() - $this->config->invoice->expired - (3600 * 5);
//        $this->createInvoice($expires5, time() - 60*60*24*1);
//        
//        $expires6 = time() - $this->config->invoice->expired - (3600 * 6);
//        $this->createInvoice($expires6, time());
        
        $invoices = Invoices::find();
        echo '<pre>' .print_r($invoices, 1) . '</pre>'; exit;
    }
    
    /**
     * create test Invoices
     */
    private function createInvoice($expires, $crated, $requested = false)
    {
        var_dump('asdasd');
        $invoice = new Invoices();
        
        $invoice->expires           = $expires;
        $invoice->created           = $crated;
        $invoice->requested         = $requested;
        $invoice->is_in_statistic   = false;
        $invoice->payer             = false;
        $invoice->amount            = 1 ?? null;
        $invoice->asset             = 'UAH'  ?? null;
        $invoice->account           = 'GDWWTT7NBH52BAAFHIQR45IRPFYQSKSKU4NIFJ5DHWG3IGVZ7KMAV4U4';
        $invoice->memo              =  null; 
        
        try {            
            $invoice->create();            
        } catch (Exeption $e) {
            $this->logger->error("Invoice create error: " . (string)$message);
            return false;
        }
    }
    
    /**
     * remove all test Invoices
     */
    public function delinvoiceAction()
    {
        
        $invoices = Invoices::find();        
        foreach ($invoices as $item) {
            $obj = Invoices::findFirst($item->id);
            $obj->delete();
        }
        
       
        $invoices = Invoices::find();
        echo '<pre>' .print_r($invoices, 1) . '</pre>';
        die('deleted invoices');
    }
    
    /**
    * remove all InvoicesStatistic
    */
    public function delinvstAction()
    {
        
        $invoices_st = InvoicesStatistic::find();        
        foreach ($invoices_st as $item) {
            
            try { 
                $obj = InvoicesStatistic::findFirst($item->date);
                $obj->delete(); 
            } catch (Exeption $e) {
               $this->logger->error('Failed to delete statistic -> ' . $e->getMessage()); 
            }
        }
        $invoicesSt = InvoicesStatistic::find();
        echo '<pre>' .print_r($invoicesSt, 1) . '</pre>';
        die('deleted statistic');
       
    }
    
    
   
}