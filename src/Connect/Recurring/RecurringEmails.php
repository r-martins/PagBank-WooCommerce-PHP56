<php
namespace RM_PagBank\Connect\Recurring;

// use stdClass; // PHP 5.6 compatibility
// use WC_Email; // PHP 5.6 compatibility

class RecurringEmails extends WC_Email
{
    public function mergePlaceholders(stdClass $subscription)
    {
        foreach ($subscription as $key => $value)
        {
            $this->placeholders array('{'.$key.'}') = $value;
        }
    }
}