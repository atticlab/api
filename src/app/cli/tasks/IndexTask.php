<?php

use \App\Models\Invoices;
use App\Models\InvoicesStatistic;

class IndexTask extends TaskBase
{
    public function statisticsAction()
    {
        $count_expired = 0; 
        $count_used = 0; 
        $count_all = 0;
        
        //get all invoices
        try {            
            $invoices = Invoices::find();         
        } catch (Exeption $e) {
            $this->logger->error("Invoices finding error: " . (string)$message);
            return false;
        }
        
        //start bot
        if (count($invoices) > 0) {
            $this->logger->info("Bot started");
            
            $statistic = array();
            
            foreach ($invoices as $invoice_code) {
                $invoice = Invoices::findFirst($invoice_code->id);
                
                $created_time = $invoice_code->created - $invoice_code->created % 86400;               
                $statistic[$created_time]['invoices'][] = $invoice_code;
                
                if (empty($statistic[$created_time]['used'])) {
                    $statistic[$created_time]['used'] = 0;
                }
                
                if (empty($statistic[$created_time]['expired'])) {
                    $statistic[$created_time]['expired'] = 0;
                }
                
                //overdue invoices
                if (is_numeric($invoice_code->requested) ) {
                    $statistic[$created_time]['used']++;
                    $invoice->is_in_statistic = true;
                //used invoices
                } elseif ( time() > $invoice_code->expires ) {                    
                    $statistic[$created_time]['expired']++;
                    $invoice->is_in_statistic = true;
                }
                $statistic[$created_time]['all'] = $statistic[$created_time]['used'] + $statistic[$created_time]['expired'];
                
                $invoice->update();
                
            }
            
            foreach ($statistic as $time => $item) {
                //create statistic                
                $count_expired = ($item['expired']) ? $item['expired'] : 0;
                $count_used = ($item['used']) ? $item['used'] : 0;
                $count_all = ($item['all']) ? $item['all'] : 0;
                
                $this->createStatistic($count_expired, $count_used, $count_all, $time);
               
            }
            
            //remove all invoices is_in_statistic = 1
            $this->removeInvoiceInStatistic($invoices);
    
            $this->logger->info("Statistics bot finished");
        }
    }
    
    private function createStatistic($expired, $used, $all, $date) 
    {       
        
        //find statistics by date
        //if there is update
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
        //if not statistics create
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
    
    
    private function removeInvoiceInStatistic($invoices)
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
    
}
