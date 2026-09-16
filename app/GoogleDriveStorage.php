<?php

namespace App;

use App\Models\Setting;
use App\Models\User;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\UploadedFile;
use JsonException;
use RuntimeException;

class GoogleDriveStorage
{
    private const OAUTH_TOKEN_KEY = 'google_drive.oauth_token';

    public function authorizationUrl(string $state): string
    {
        $client = $this->oauthClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function completeAuthorization(string $authorizationCode, User $user): void
    {
        $client = $this->oauthClient();
        $token = $client->fetchAccessTokenWithAuthCode($authorizationCode);

        if (isset($token['error']) || ! isset($token['access_token'])) {
            throw new RuntimeException('Google Drive menolak otorisasi. Hubungkan ulang akun Google Drive.');
        }

        if (! isset($token['refresh_token'])) {
            $existingToken = $this->oauthToken();

            if (! isset($existingToken['refresh_token'])) {
                throw new RuntimeException('Google Drive tidak memberikan akses berkelanjutan. Hapus akses aplikasi di Akun Google lalu hubungkan kembali.');
            }

            $token['refresh_token'] = $existingToken['refresh_token'];
        }

        $this->storeOauthToken($token, $user->id);
    }

    public function isConnected(): bool
    {
        return config('services.google_drive.enabled') && $this->oauthToken() !== null;
    }

    public function upload(UploadedFile $file, string $sourceType): array
    {
        $folderId = $this->folderIdFor($sourceType);
        $drive = new Drive($this->client());
        $uploadedFile = $drive->files->create(new DriveFile([
            'name' => $file->getClientOriginalName(),
            'parents' => [$folderId],
        ]), [
            'data' => file_get_contents($file->getRealPath()),
            'mimeType' => $file->getMimeType(),
            'uploadType' => 'multipart',
            'fields' => 'id,name,webViewLink',
            'supportsAllDrives' => true,
        ]);

        return ['id' => $uploadedFile->id, 'name' => $uploadedFile->name, 'url' => $uploadedFile->webViewLink];
    }

    public function folderIsAccessible(string $folderId): bool
    {
        $drive = new Drive($this->client());
        $drive->files->get($folderId, ['fields' => 'id', 'supportsAllDrives' => true]);

        return true;
    }

    private function client(): Client
    {
        $client = $this->oauthClient();
        $token = $this->oauthToken();

        if ($token === null) {
            throw new RuntimeException('Google Drive belum dihubungkan.');
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();

            if ($refreshToken === null) {
                throw new RuntimeException('Sesi Google Drive telah berakhir. Hubungkan kembali Google Drive.');
            }

            $refreshedToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($refreshedToken['error']) || ! isset($refreshedToken['access_token'])) {
                throw new RuntimeException('Sesi Google Drive tidak dapat diperbarui. Hubungkan kembali Google Drive.');
            }

            $this->storeOauthToken($refreshedToken);
        }

        return $client;
    }

    private function oauthClient(): Client
    {
        $credentials = config('services.google_drive.oauth_credentials');
        $redirectUri = config('services.google_drive.oauth_redirect_uri');

        if (! config('services.google_drive.enabled') || ! is_string($credentials) || ! is_file($credentials) || ! is_string($redirectUri) || $redirectUri === '') {
            throw new RuntimeException('Kredensial OAuth Google Drive belum dikonfigurasi dengan benar.');
        }

        $client = new Client;
        $client->setAuthConfig($credentials);
        $client->setScopes([Drive::DRIVE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setRedirectUri($redirectUri);

        return $client;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function oauthToken(): ?array
    {
        $setting = Setting::query()->where('key', self::OAUTH_TOKEN_KEY)->first();

        if ($setting === null || $setting->value === null) {
            return null;
        }

        try {
            $token = json_decode($setting->value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Token Google Drive tidak valid. Hubungkan kembali Google Drive.');
        }

        return is_array($token) ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function storeOauthToken(array $token, ?int $updatedBy = null): void
    {
        $setting = Setting::query()->firstOrNew(['key' => self::OAUTH_TOKEN_KEY]);
        $setting->value = json_encode($token, JSON_THROW_ON_ERROR);

        if ($updatedBy !== null) {
            $setting->updated_by = $updatedBy;
        }

        $setting->save();
    }

    private function folderIdFor(string $sourceType): string
    {
        $key = match ($sourceType) {
            'projects' => 'projects_folder_id',
            'lkpm' => 'lkpm_folder_id',
            'sectors' => 'sectors_folder_id',
            default => throw new RuntimeException('Jenis sumber impor tidak dikenali.'),
        };
        $folderId = config('services.google_drive.'.$key);
        if (! is_string($folderId) || $folderId === '') {
            throw new RuntimeException('Folder Google Drive untuk impor belum dikonfigurasi.');
        }

        return $folderId;
    }
}
