<div class="bg-light mt-3 p-3 border rounded">
    <h4>How to retrieve your credentials</h4>
    <ol class="pl-3 text-left">
        <li>
            <p>Create an
                <code>App ID</code> for your website (<a href="https://developer.apple.com/account/resources/identifiers/list/bundleId" rel="nofollow" target="_blank">https://developer.apple.com/account/resources/identifiers/list/bundleId</a>) with the following details:
            </p>
            <ul class="pl-3">
                <li>Platform: iOS, tvOS, watchOS (I'm unsure if either choice has an effect for web apps)</li>
                <li>Description: (something like "example.com app id")</li>
                <li>Bundle ID (Explicit): com.example.id (or something similar)</li>
                <li>Check "Sign In With Apple"</li>
            </ul>
        </li>
        <li>
            <p>Create a
                <code>Service ID</code> for your website (<a href="https://developer.apple.com/account/resources/identifiers/list/serviceId" rel="nofollow" target="_blank">https://developer.apple.com/account/resources/identifiers/list/serviceId</a>) with the following details:
            </p>
            <ul class="pl-3">
                <li>Description: (something like "example.com service id")</li>
                <li>Identifier: com.example.service (or something similar)</li>
                <li>Check "Sign In With Apple"</li>
                <li>Configure "Sign In With Apple":
                    <ul class="pl-3">
                        <li>Primary App Id: (select the primary app id created in step 1)</li>
                        <li>Web Domain:
                            <span style="color:green">{{ url()->to('/') }}</span> (the domain of your web site)
                        </li>
                        <li>Return URLs:
                            <span style="color:green">{{ $formModel->getProvider('sign-in-with-apple')->makeEntryPointUrl('callback') }}</span>
                        </li>
                        <li>Click "Save".</li>
                        <li>Click the "Edit" button to edit the details of the "Sign In With Apple"
                            configuration we just created.
                        </li>
                        <li>If you haven't verified the domain yet, download the verification file,
                            upload it to
                            <span style="color:green">{{ url()->to('/.well-known/apple-developer-domain-association.txt') }}</span>, and then click the "Verify"
                            button.
                        </li>
                    </ul>
                </li>
            </ul>
        </li>
        <li>
            <p>Create a
                <code>Private Key</code> for your website (<a href="https://developer.apple.com/account/resources/authkeys/list" rel="nofollow" target="_blank">https://developer.apple.com/account/resources/authkeys/list</a>) with the following details:
            </p>
            <ul class="pl-3">
                <li>Key Name:</li>
                <li>Check "Sign In With Apple"</li>
                <li>Configure "Sign In With Apple":
                    <ul class="pl-3">
                        <li>Primary App ID: (select the primary app id created in step 1)</li>
                        <li>Click "Save"</li>
                    </ul>
                </li>
                <li>Click "Continue"</li>
                <li>Click "Register"</li>
                <li>Click "Download"</li>
                <li>Copy the contents of the downloaded file and paste it above</li>
            </ul>
        </li>
    </ol>
</div>
