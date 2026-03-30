<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script src="https://code.jquery.com/jquery-4.0.0.min.js" integrity="sha256-OaVG6prZf4v69dPg6PhVattBXkcOWQB62pdZ3ORyrao=" crossorigin="anonymous"></script>
<script>
    document.addEventListener('livewire:initialized', () => {

        $(document).ready(function() {

            // 🔔 Unified Toastify function
            function showToast(message, type = "success") {
                let background;

                switch (type) {
                    case "success":
                        background = "linear-gradient(to right, #00b09b, #96c93d)"; // Green
                        break;
                    case "danger":
                        background = "linear-gradient(to right, #ff416c, #ff4b2b)"; // Red
                        break;
                    case "warning":
                        background = "linear-gradient(to right, #f7971e, #ffd200)"; // Yellow/Orange
                        break;
                    default:
                        background = "linear-gradient(to right, #00b09b, #96c93d)"; // Default Green
                }

                Toastify({
                    text: message,
                    close: true,
                    gravity: "top",
                    position: "right",
                    stopOnFocus: true,
                    style: {
                        background,
                    },
                }).showToast();
            }

            // ✅ Success
            $(document).on("success", (event) => {
                showToast(event.detail[0].message, "success");
            });

            // ❌ Error
            $(document).on("danger", (event) => {
                showToast(event.detail[0].message, "danger");
            });

            // ⚠️ Warning
            $(document).on("warning", (event) => {
                showToast(event.detail[0].message, "warning");
            });
        });

    });
</script>
