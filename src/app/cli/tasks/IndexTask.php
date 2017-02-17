<?php

use \App\Models\Invoices;
use App\Models\InvoicesStatistic;
use Phalcon\DI;

class IndexTask extends TaskBase
{
    public function initAction(){

        //create indexes for all models
        $ignore_files = [
            'ModelBase.php',
            'ModelInterface.php'
        ];
        $files = array_diff(scandir(MODEL_PATH), array_merge(array('.', '..'), $ignore_files));

        $config = DI::getDefault()->get('config');
        $riak   = DI::getDefault()->get('riak');

        //create indexes
        foreach ($files as $file) {

            $bucket_name = mb_strtolower(str_replace('.php', '', $file));
            $index_name  = $bucket_name . $config->riak->search_index_suffics;

            //create search index
            (new \Basho\Riak\Command\Builder\Search\StoreIndex($riak))
                ->withName($index_name)
                ->build()
                ->execute();
            sleep(3);

            //create search index for debug
            $bucket_name = 'debug_' . mb_strtolower(str_replace('.php', '', $file));
            $index_name  = $bucket_name . $config->riak->search_index_suffics;

            //create search index and associate it with bucket
            (new \Basho\Riak\Command\Builder\Search\StoreIndex($riak))
                ->withName($index_name)
                ->build()
                ->execute();
            sleep(3);
        }

        //associate buckets with indexes
        foreach ($files as $file) {

            $bucket_name = mb_strtolower(str_replace('.php', '', $file));
            $index_name  = $bucket_name . $config->riak->search_index_suffics;

            (new \Basho\Riak\Command\Builder\Search\AssociateIndex($riak))
                ->withName($index_name)
                ->buildBucket($bucket_name)
                ->build()
                ->execute();
            sleep(3);

            //for debug
            $bucket_name = 'debug_' . mb_strtolower(str_replace('.php', '', $file));
            $index_name  = $bucket_name . $config->riak->search_index_suffics;

            (new \Basho\Riak\Command\Builder\Search\AssociateIndex($riak))
                ->withName($index_name)
                ->buildBucket($bucket_name)
                ->build()
                ->execute();
            sleep(3);
        }
    }

    public function statisticsAction()
    {
        $statistic = array();

        //get expired invoices
        try {            
            $invoices = Invoices::findExpiredInvoices();
        } catch (Exeption $e) {
            $this->logger->error("Invoices finding error: " . $e->getMessage());
            return false;
        }

        if (count($invoices) > 0) {
            foreach ($invoices as $expired) {
                $invoice = Invoices::findFirst($expired->id);
                
                $created_time = $expired->created - $expired->created % 86400;
                $statistic[$created_time]['invoices'][] = $expired;

                if (empty($statistic[$created_time]['expired'])) {
                    $statistic[$created_time]['expired'] = 0;
                }

                $statistic[$created_time]['expired']++;
                $invoice->is_in_statistic_b = true;
                
                $invoice->update();
            }
        }

        //get used invoices
        try {
            $invoices = Invoices::findUsedInvoices();
        } catch (Exeption $e) {
            $this->logger->error("Invoices finding error: " . $e->getMessage());
            return false;
        }

        if (count($invoices) > 0) {
            foreach ($invoices as $used) {
                $invoice = Invoices::findFirst($used->id);

                $created_time = $used->created - $used->created % 86400;
                $statistic[$created_time]['invoices'][] = $used;

                if (empty($statistic[$created_time]['used'])) {
                    $statistic[$created_time]['used'] = 0;
                }

                $statistic[$created_time]['used']++;
                $invoice->is_in_statistic_b = true;

                $invoice->update();
            }
        }

        foreach ($statistic as $time => $item) {
            //create statistic
            $count_expired = !empty($item['expired']) ? $item['expired'] : 0;
            $count_used    = !empty($item['used'])    ? $item['used']    : 0;
            $count_all     = $count_expired + $count_used;
            $this->createStatistic($count_expired, $count_used, $count_all, $time);
        }

        //wait for solr indexing delay
        sleep(5);
        $this->removeInvoiceInStatistic();
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
            } catch (Exception $e) {
                $this->logger->error('There is an error of saving Statistic.' . $e->getMessage());
            }           
        }
    }
    
    
    private function removeInvoiceInStatistic()
    {
        $invoices = Invoices::findInvoicesInStatistic();
        foreach ($invoices as $item) {
            try {
                $obj = Invoices::findFirst($item->id);
                $obj->delete();
            } catch (Exeption $e) {
                $this->logger->error("Invoices in statistic delete error: " . $e->getMessage());
            }
        }
    } 
    
}
