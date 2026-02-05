import { useTheme } from "next-themes";
import { Toaster as Sonner, toast } from "sonner";

type ToasterProps = React.ComponentProps<typeof Sonner>;

const Toaster = ({ ...props }: ToasterProps) => {
  const { theme = "system" } = useTheme();

  return (
    <Sonner
      theme={theme as ToasterProps["theme"]}
      className="toaster group"
      position="bottom-right"
      richColors
      closeButton
      toastOptions={{
        classNames: {
          // Solid dark gray background matching reference image - rounded corners, shadow
          toast:
            "group toast group-[.toaster]:bg-[hsl(220,13%,14%)] group-[.toaster]:text-[hsl(210,40%,98%)] group-[.toaster]:border group-[.toaster]:border-[hsl(220,13%,22%)] group-[.toaster]:shadow-xl group-[.toaster]:rounded-xl group-[.toaster]:p-4",
          // Slightly dimmed description text
          description: "group-[.toast]:text-[hsl(215,20%,65%)] group-[.toast]:text-sm",
          // Action button styling - solid with primary color
          actionButton: "group-[.toast]:bg-primary group-[.toast]:text-primary-foreground group-[.toast]:rounded-lg group-[.toast]:font-medium group-[.toast]:px-3 group-[.toast]:py-1.5",
          // Cancel/dismiss button - outlined style
          cancelButton: "group-[.toast]:bg-transparent group-[.toast]:border group-[.toast]:border-[hsl(220,13%,30%)] group-[.toast]:text-[hsl(215,20%,70%)] group-[.toast]:rounded-lg group-[.toast]:hover:bg-[hsl(220,13%,20%)]",
          // Red close button - positioned top right, larger touch target on mobile
          closeButton: "group-[.toast]:!bg-[hsl(0,62%,45%)] group-[.toast]:!border-[hsl(0,62%,35%)] group-[.toast]:!text-white hover:group-[.toast]:!bg-[hsl(0,72%,50%)] group-[.toast]:!rounded-full group-[.toast]:!w-6 group-[.toast]:!h-6 sm:group-[.toast]:!w-5 sm:group-[.toast]:!h-5 group-[.toast]:!-right-1 group-[.toast]:!-top-1 group-[.toast]:!shadow-md",
          // Status-specific styling - solid backgrounds with matching border and text
          success: "group-[.toaster]:!bg-[hsl(142,35%,16%)] group-[.toaster]:!border-[hsl(142,40%,25%)] group-[.toaster]:!text-[hsl(142,70%,75%)]",
          error: "group-[.toaster]:!bg-[hsl(0,35%,16%)] group-[.toaster]:!border-[hsl(0,40%,30%)] group-[.toaster]:!text-[hsl(0,70%,75%)]",
          warning: "group-[.toaster]:!bg-[hsl(38,35%,16%)] group-[.toaster]:!border-[hsl(38,40%,28%)] group-[.toaster]:!text-[hsl(38,80%,70%)]",
          info: "group-[.toaster]:!bg-[hsl(210,35%,16%)] group-[.toaster]:!border-[hsl(210,40%,28%)] group-[.toaster]:!text-[hsl(210,70%,75%)]",
          // Title styling
          title: "group-[.toast]:text-base group-[.toast]:font-semibold",
        },
      }}
      {...props}
    />
  );
};

export { Toaster, toast };
