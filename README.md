# IPN Adapter
This is currently a quick-and-dirty solution to connect Digistore with Brevo to add buyers to mailing-lists.

## Why?
Digitsore currently does not support Brevo natively. So if you want to add buyers of your products to your mailing-lists, there is currently no eays (and free of extra charge) way to do this. So we had to find another solution. As there already was website based on Wordpress, the easiest way was to write a small pseudo-plugin for Wordpress, that acts as proxy between Digistore and Brevo.

## How?
If you add these files as plugin to your Wordpress, the endpoint will be `https://<YOUR-SERVER>/wp-content/plugins/ipn-adapter/ipn-adapter.php`.
To add this code as a plugin, create a zip-file of this folder and upload it manually as plugin to your Wordpress. Be sure to have the folder (`ipn-adapter`) in your ZIP-file and not only the plain files.

## Setup of IPN-Adapter
Copy the `settings.php.template`-file to `settings.php` and open it in an editor. Set the values as follows:
| Key | Description | Example |
|-----|-------------|---------|
| `BREVO_SECRET` | API-key from Brevo. Be aware that you need to add the IP-address of your Wordpress-server to the allowed addresses| `"some-secret-api-key"` |
| `DIGISTORE_SECRET` | The password you will set in the generic IPN configuration in Digistore | `whatever-password` |
| `NEWSLETTER_LIST_ID` | Brevo list ID of your newsletter list. If `AddToNewsLetterList` is enabled, the buyers email will be added to that list | 123 |
| `COURSE_LIST_ID_<ProductId>`| Be sure to replace `<ProductId>` by the Digistore Product-ID which you want to link to a certain mailing list. Set the Brevo list ID as value | 345 |

You can add as much `COURSE_LIST_ID_<ProductId>`-entries as you like to map several products to specific lists.

## Setup in Digistore
- Add a generic IPN 
- Be sure to deactivate grouping of calls
- This has not been tested with any AddOn products

## Bookmarks
- Brevo API docu for "createcontact": https://developers.brevo.com/reference/createcontact
- Digistore event docu: https://dev.digistore24.com/hc/en-us/articles/32480561422353-Events