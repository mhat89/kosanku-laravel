try {
    $response = Invoke-RestMethod -Uri 'http://localhost:8001/api/admin/verify-otp' -Method POST -Headers @{
        'Accept' = 'application/json'
        'Content-Type' = 'application/json'
    } -Body '{"email":"admin@kosanku.com","code":"160424"}'
    
    Write-Output "Success:"
    Write-Output $response
} catch {
    Write-Output "Error Status: $($_.Exception.Response.StatusCode)"
    
    if ($_.Exception.Response) {
        $stream = $_.Exception.Response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        $errorBody = $reader.ReadToEnd()
        Write-Output "Error Body: $errorBody"
    }
}