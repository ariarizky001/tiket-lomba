<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Facades\Log;

class GoogleSheetsService
{
    protected $client;
    protected $service;

    public function __construct()
    {
        $this->client = new Client();
        
        $credentialsPath = config('services.google.credentials_path');
        
        Log::info('GoogleSheetsService init', [
            'config_path' => $credentialsPath,
            'base_path' => base_path(),
            'file_exists_relative' => $credentialsPath ? file_exists($credentialsPath) : false,
            'file_exists_full' => $credentialsPath ? file_exists(base_path($credentialsPath)) : false,
        ]);
        
        if ($credentialsPath && file_exists($credentialsPath)) {
            $this->client->setAuthConfig($credentialsPath);
            Log::info('Loaded credentials from relative path');
        } elseif ($credentialsPath && file_exists(base_path($credentialsPath))) {
            $this->client->setAuthConfig(base_path($credentialsPath));
            Log::info('Loaded credentials from full path');
        } else {
            Log::error('Google Sheets credentials file not found', [
                'relative_path' => $credentialsPath,
                'full_path' => base_path($credentialsPath ?? ''),
            ]);
        }
        
        $this->client->addScope(Sheets::SPREADSHEETS);
        $this->service = new Sheets($this->client);
    }

    public function updateValues(string $spreadsheetId, string $range, array $values): bool
    {
        try {
            $body = new ValueRange([
                'values' => $values
            ]);
            
            $params = [
                'valueInputOption' => 'RAW'
            ];
            
            $this->service->spreadsheets_values->update(
                $spreadsheetId,
                $range,
                $body,
                $params
            );
            
            return true;
        } catch (\Exception $e) {
            Log::error('Google Sheets update error: ' . $e->getMessage());
            return false;
        }
    }

    public function clearValues(string $spreadsheetId, string $range): bool
    {
        try {
            $this->service->spreadsheets_values->clear($spreadsheetId, $range, new \Google\Service\Sheets\ClearValuesRequest());
            return true;
        } catch (\Exception $e) {
            Log::error('Google Sheets clear error: ' . $e->getMessage());
            return false;
        }
    }

    public function getValues(string $spreadsheetId, string $range): ?array
    {
        try {
            $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
            return $response->getValues();
        } catch (\Exception $e) {
            Log::error('Google Sheets get error: ' . $e->getMessage());
            return null;
        }
    }

    public function testConnection(string $spreadsheetId): array
    {
        try {
            $spreadsheet = $this->service->spreadsheets->get($spreadsheetId);
            
            return [
                'success' => true,
                'title' => $spreadsheet->getProperties()->getTitle(),
                'sheets' => collect($spreadsheet->getSheets())->map(function ($sheet) {
                    return [
                        'name' => $sheet->getProperties()->getTitle(),
                        'id' => $sheet->getProperties()->getSheetId(),
                    ];
                })->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
