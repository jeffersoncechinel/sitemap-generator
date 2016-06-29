<?php

namespace app\commands;

use yii\console\Controller;
use yii\helpers\Console;
use yii\db\Query;
use Yii;
use XMLWriter;
use app\models\Sitemap;
use app\models\Custom;
use GuzzleHttp\Client;

class SitemapController extends Controller
{

    private $lastUpdateKey = 'sitemap-content-last-update';
    private $webRoot;
    private $create;


    public function actionIndex()
    {
        echo "Usage: yii sitemap/init\n";
        echo "Usage: yii sitemap/init now\n";
        echo "Usage: yii sitemap/init now all (inital start)\n";

        return true;
    }

    public function actionInit($when = null, $create = null)
    {
        $this->create = $create;

        $totalTasks = 5;
        Console::startProgress(0, $totalTasks, 'Progress: ', false);

        if (!$when == 'now') {
            $lastUpdate = Yii::$app->cache->get($this->lastUpdateKey);
            $currentLast = (new Query())->select('max(last_modified)')->from('slugs')->scalar();

            if ($lastUpdate == $currentLast) {
                echo("\nAborting. No update needed\n\n");
                return true;
            }
            Console::updateProgress(1, $totalTasks);

            Yii::$app->cache->set($this->lastUpdateKey, $currentLast);
        }

        $beginTime = microtime(true);
        $this->webRoot = \Yii::$app->params['webRoot'];

        //CREATING CONTENT INDEX
        Console::updateProgress(2, $totalTasks);
        $sitemaps = Sitemap::findAll(['is_active' => 1, 'is_child' => 1, 'is_static' => 0]);
        foreach ($sitemaps as $sitemap) {
            //$this->generateContentIndex($sitemap);
            $this->generateSiteIndex($sitemap, Custom::contentIndex());
        }

        //CREATING SITE INDEX
        Console::updateProgress(3, $totalTasks);
        $sitemaps = Sitemap::findAll(['is_active' => 1, 'is_child' => 1, 'is_static' => 1]);
        foreach ($sitemaps as $sitemap) {
            $this->generateSiteIndex($sitemap, Custom::siteIndex());
        }

        //CREATING SITEMAP
        Console::updateProgress(4, $totalTasks);
        $sitemaps = Sitemap::findAll(['is_active' => 1, 'is_child' => 0]);
        foreach ($sitemaps as $sitemap) {
            $this->generateSitemap($sitemap);
        }

        //INFORMING GOOGLE ABOUT THE NEW SITEMAP
        Console::updateProgress(5, $totalTasks);
        if(\Yii::$app->params['pingGoogle']) {
            $this->pingGoogle(\Yii::$app->params['url']);
        }

        Console::endProgress();
        $duration = microtime(true) - $beginTime;
        echo("Script generated in {$duration} seconds.\n\n");
    }

    private function generateSitemap($sitemap)
    {
        $sitemapFile = $this->webRoot . $sitemap->filename;

        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $children = Sitemap::findAll(['is_active' => 1, 'is_child' => 1]);
        foreach ($children as $child) {
            $extension = '';
            if ($child->is_compressed == 1) {
                $extension = '.gz';
            }

            $path = $this->webRoot . $child->filename . $extension;
            $writer->startElement('sitemap');
            $writer->writeElement('loc', \Yii::$app->params['url'] . $child->filename . $extension);

            if (!file_exists($this->webRoot . $child->filename . $extension) || $this->create == 'all') {
                $writer->writeElement('lastmod', $this->getNow());
            } else {
                $writer->writeElement('lastmod', date('c', filemtime($path)));
            }
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        if ($sitemap->is_compressed == 1) {
            $gzfile = $sitemapFile . '.gz';
            $fp = gzopen($gzfile, 'w9');
            gzwrite($fp, $writer->outputMemory(true));
            gzclose($fp);
        } else {
            $fp = fopen($sitemapFile, 'c');
            fwrite($fp, $writer->outputMemory(true));
            fclose($fp);
        }
    }

    private function generateContentIndex($sitemap)
    {
        $sitemapFile = $this->webRoot . $sitemap->filename;
        $writer = new XMLWriter();

        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation', 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');

        //GETTING DYNAMIC CONTENT URLS
        $urls = Custom::siteContent();
        foreach ($urls as $row) {
            $writer->startElement('url');
            $writer->writeElement('loc', $row);
            $writer->writeElement('priority', $sitemap['priority']);
            $writer->writeElement('changefreq', $sitemap['changefreq']);
            $writer->writeElement('lastmod', $this->getNow());
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        if ($sitemap->is_compressed == 1) {
            $gzfile = $sitemapFile . '.gz';
            $fp = gzopen($gzfile, 'w9');
            gzwrite($fp, $writer->outputMemory(true));
            gzclose($fp);
        } else {
            $fp = fopen($sitemapFile, 'c');
            fwrite($fp, $writer->outputMemory(true));
            fclose($fp);
        }
    }

    private function generateSiteIndex($sitemap, $urls)
    {
        $extension = '';
        if ($sitemap->is_compressed == 1) {
            $extension = '.gz';
        }

        if($sitemap->is_static == 1) {
            if (!file_exists($this->webRoot . $sitemap->filename . $extension) || $this->create == 'all') {
               $this->xmlTemplate($sitemap, $urls);
            }
        } else {
            $this->xmlTemplate($sitemap, $urls);
        }
    }

    private function xmlTemplate($sitemap, $urls)
    {
        $sitemapFile = $this->webRoot . $sitemap->filename;

        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation', 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');

        foreach ($urls as $row) {
            $writer->startElement('url');
            $writer->writeElement('loc', $row);
            $writer->writeElement('priority', $sitemap['priority']);
            $writer->writeElement('changefreq', $sitemap['changefreq']);
            $writer->writeElement('lastmod', $this->getNow());
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        if ($sitemap->is_compressed == 1) {
            $gzfile = $sitemapFile . '.gz';
            $fp = gzopen($gzfile, 'w9');
            gzwrite($fp, $writer->outputMemory(true));
            gzclose($fp);
        } else {
            $fp = fopen($sitemapFile, 'c');
            fwrite($fp, $writer->outputMemory(true));
            fclose($fp);
        }
    }

    private function pingGoogle($host)
    {
        $client = new Client();

        $url = 'http://www.google.com/webmasters/tools/ping?sitemap=https%3A%2F%2F' . $host . '%2Fsitemap.xml';
        echo "\nPinging google: ". $url . "\n";
        $client->get($url);
    }

    private function getNow()
    {
        $now = new \DateTime('now');

        return $now->format('Y-m-d\TH:i:sP');
    }

}