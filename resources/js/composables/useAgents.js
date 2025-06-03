import { router, useForm } from "@inertiajs/vue3";
import { toast } from "vue-sonner";
import { usePage  as page} from "@inertiajs/vue3";

export function useAgentForm(initialData = null) {

    const form = useForm({
      agent_name: initialData?.agent_name || '',
      agent_lname: initialData?.agent_lname || '',
      agent_phone: initialData?.agent_phone || '',
      agent_mail: initialData?.agent_mail || '',
      agent_status: initialData?.agent_status ?? true,
    })

    const createAgent = () => {
      form.post(route('agents.store'), {
        preserveScroll: true,
        preserveState: true,
        
        onSuccess: () => {
            if (page().props.flash.success) {
              toast.success(page().props.flash.success)
            }
          },
        onError: (errors) => {
            if (errors.agent) {
                toast.error(errors.agent);
            } else {
                toast.error('algo no salio bien')
            }
        }
      })
    }

    const updateAgent = (id) => {
       // const page = usePage()

      form.put(route('agents.update', id), {

        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (page().props.flash.success) {
              toast.success(page().props.flash.success)
            }
          },

        onError: (errors) => {
            if (errors.agent) {
                toast.error(errors.agent);
            } else {
                toast.error('algo no salio bien')
            }
        }
      })
    }

    const deleteAgent = (id) => {
        //if(!window.confirm('Seguro de que quieres Eliminar este Agente?')) return;

        router.delete(route('agents.destroy', id), {
            preserveScroll: true,

            onError: (errors) => {
                if (errors.agent) {
                    toast.error(errors.agent);
                } else {
                    toast.error('algo no salio bien')
                }
            }

        });
    }

    return {
      form,
      createAgent,
      updateAgent,
      deleteAgent
    }
  }

