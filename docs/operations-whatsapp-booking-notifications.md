# WhatsApp booking notifications

## Runtime setup

Configure the platform's Meta WhatsApp Cloud API sender in the deployment environment:

```dotenv
WHATSAPP_GRAPH_VERSION=v23.0
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_BOOKING_TEMPLATE=booking_received_clinic
WHATSAPP_TEMPLATE_LANGUAGE=ms
```

Run the database migration and keep a worker consuming the `notifications` queue.

## Meta message template

Create and obtain Meta approval for a template named `booking_received_clinic`. Its body must use
the following variables in this exact order:

1. Patient name
2. Booking reference
3. Appointment date
4. Appointment time
5. Service name
6. Patient phone

Suggested Malay body:

```text
Tempahan baharu diterima.

Pesakit: {{1}}
Rujukan: {{2}}
Tarikh: {{3}}
Masa: {{4}}
Servis: {{5}}
Telefon: {{6}}

Sila buka dashboard SYIFA.my untuk menyemak dan mengesahkan tempahan.
```

Do not add patient notes or medical details to the WhatsApp template. Full booking information
remains in the authenticated clinic dashboard.

## Clinic controls

The clinic owner opens **Bookings → Set Booking Hours**, enters the recipient number under
**WhatsApp booking notifications**, enables the checkbox, and saves. Only new bookings submitted
from the public website are sent. Turning the setting off cancels queued deliveries that have not
started; staff-created manual bookings never create a WhatsApp delivery.

Delivery state is recorded in `booking_whatsapp_deliveries`. Failed provider calls are retried by
the queue without rolling back or duplicating the booking record.
