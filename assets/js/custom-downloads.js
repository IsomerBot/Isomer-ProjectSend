/**
 * Custom downloads JavaScript functions
 */

function copyTextToClipboard(text) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(
      function () {
        alert("Download link copied to clipboard!");
      },
      function (err) {
        console.error("Could not copy text: ", err);
      },
    );
  } else {
    // Fallback for older browsers
    const textArea = document.createElement("textarea");
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
      document.execCommand("copy");
      alert("Download link copied to clipboard!");
    } catch (err) {
      console.error("Fallback: Could not copy text: ", err);
    }
    document.body.removeChild(textArea);
  }
}

// Apply custom download alias to all files
function applyToAllCustomDownloads(sourceInputId) {
  const sourceInput = document.getElementById(sourceInputId);
  const sourceValue = sourceInput ? sourceInput.value.trim() : "";

  if (!sourceValue) {
    alert(
      "Please enter a value in the first custom download field before applying to all.",
    );
    return;
  }

  // Apply to all custom download inputs (but with unique suffixes)
  document
    .querySelectorAll('[id^="custom_download_input_"]')
    .forEach(function (input, index) {
      if (sourceValue && index > 0) {
        // Add index suffix to make unique
        input.value = sourceValue + "-" + (index + 1);
      } else if (index === 0) {
        input.value = sourceValue;
      }
    });

  alert("Custom download alias applied to all files (with unique suffixes)");
}
