<x-mail::message>
# Approval OTP

Hello **{{ $restaurantName }}**,

Your OTP for approving the pending item is:

# **{{ $otpCode }}**

This OTP expires in **{{ $expiresInMinutes }} minutes**.

If you did not request this, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
