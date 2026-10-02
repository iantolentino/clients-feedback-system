param(
    [string]$FormUrl = 'http://localhost/client-feedback/feedback.php'
)

$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$page = Invoke-WebRequest -Uri $FormUrl -WebSession $web -UseBasicParsing
$csrfMatch = [regex]::Match($page.Content, 'name="csrf" value="([^"]+)"')
if (-not $csrfMatch.Success) { throw 'Could not find the feedback CSRF token.' }

$fields = @{
    csrf = $csrfMatch.Groups[1].Value
    client_name = 'Automated Test Client 5'
    poc_name = 'Automated Test POC'
    relationship_score = '5'
    business_needs_score = '4'
    communication_support_score = '3'
    concerns_comfort_score = '4'
    partnership_confidence_score = '5'
    improvement_feedback = 'Automated test suggestion.'
    relationship_description = 'Automated test relationship description.'
}

$result = Invoke-WebRequest -Uri $FormUrl -Method Post -Body $fields -WebSession $web -UseBasicParsing
Write-Output "HTTP status: $($result.StatusCode)"
Write-Output "Thank-you response: $($result.Content -match 'Thank you for your feedback')"
Write-Output 'Check the local admin dashboard for the new Automated Test Client submission.'
