$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".eventSideA").addClass("activeLi");

    $("#eventsTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "POST",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6, 7],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}eventsList`,
            data: function (data) {},
        },
    });

    // 🛑 FUNCIÓN PARA ELIMINAR EVENTOS
    $(document).on("click", ".deleteEvent", function (e) {
        e.preventDefault();
        
        var eventId = $(this).attr("rel"); // Obtener ID del evento
        console.log("Evento a eliminar:", eventId);

        swal({
            title: "Are you sure?",
            text: "Once deleted, you will not be able to recover this event!",
            icon: "warning",
            buttons: true,
            dangerMode: true,
            buttons: ["Cancel", "Yes, delete it"],
        }).then((confirmDelete) => {
            if (confirmDelete) {
                $.ajax({
                    type: "POST",
                    url: `${domainUrl}deleteEvent`, // Ruta en Laravel
                    dataType: "json",
                    data: {
                        event_id: eventId,
                        _token: $('meta[name="csrf-token"]').attr("content"), // Token CSRF
                    },
                    success: function (response) {
                        if (response.status === false) {
                            console.log(response.message);
                        } else {
                            iziToast.show({
                                title: "Success",
                                message: "Event deleted successfully",
                                color: "green",
                                position: "topRight",
                                timeout: 3000,
                            });

                            // Recargar la tabla después de eliminar
                            $("#eventsTable").DataTable().ajax.reload(null, false);
                        }
                    },
                    error: function (error) {
                        console.log("Error:", error);
                        iziToast.show({
                            title: "Error",
                            message: "Failed to delete event",
                            color: "red",
                            position: "topRight",
                            timeout: 3000,
                        });
                    },
                });
            }
        });
    });

    // Mostrar los detalles del evento en el modal
    $("#eventsTable").on("click", ".viewEvent", function (e) {
        e.preventDefault();
        
        var eventData = {
            title: $(this).data("title"),
            organizer: $(this).data("organizer"),
            startDate: $(this).data("start-date"),
            endDate: $(this).data("end-date"),
            availableSlots: $(this).data("available-slots"),
            capacity: $(this).data("capacity"),
            status: $(this).data("status"),
            description: $(this).data("description"),
            image: $(this).data("image"),
        };

        console.log("Evento seleccionado:", eventData);

        // Mostrar en el modal
        $("#eventModalTitle").text(eventData.title);
        $("#eventModalOrganizer").text(eventData.organizer);
        $("#eventModalStartDate").text(eventData.startDate);
        $("#eventModalEndDate").text(eventData.endDate);
        $("#eventModalAvailableSlots").text(eventData.availableSlots);
        $("#eventModalCapacity").text(eventData.capacity);
        $("#eventModalStatus").text(eventData.status);
        $("#eventModalDescription").text(eventData.description);
        $("#eventModalImage").attr("src", eventData.image);

        $("#viewEventModal").modal("show");
    });
});