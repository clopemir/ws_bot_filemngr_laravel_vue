import { router, useForm } from "@inertiajs/vue3";
import { toast } from "vue-sonner";
import { usePage  as page} from "@inertiajs/vue3";

export function useClientForm(initialData = null) {

    const form = useForm({
      agent_id:  initialData?.agent_id ?? 0,
      client_name: initialData?.client_name || '',
      client_lname: initialData?.client_lname || '',
      client_rfc: initialData?.client_rfc || '',
      client_phone: initialData?.client_phone || '',
      client_mail: initialData?.client_mail || '',
      client_status: initialData?.client_status ?? true
    })

    const createClient = () => {
      form.post(route('clients.store'), {
        preserveScroll: true,
        preserveState: true,

        onSuccess: () => {
            if (page().props.flash.success) {
              toast.success(page().props.flash.success)
            }
          },
        onError: (errors) => {
            if (errors.client) {
                toast.error(errors.client);
            } else {
                toast.error('algo no salio bien')
            }
        }
      })
    }

    const updateClient = (id) => {
       // const page = usePage()

      form.put(route('clients.update', id), {

        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (page().props.flash.success) {
              toast.success(page().props.flash.success)
            }
          },

        onError: (errors) => {
            if (errors.client) {
                toast.error(errors.client);
            } else {
                toast.error('algo no salio bien')
            }
        }
      })
    }

    const deleteClient = (id) => {
        //if(!window.confirm('Seguro de que quieres Eliminar este cliente?')) return;

        router.delete(route('clients.destroy', id), {
            preserveScroll: true,

            onError: (errors) => {
                if (errors.client) {
                    toast.error(errors.client);
                } else {
                    toast.error('algo no salio bien')
                }
            }

        });
    }

    return {
      form,
      createClient,
      updateClient,
      deleteClient
    }
  }

