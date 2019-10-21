for i in 1 2 3; do
    ./vendor/bin/wpacceptance run

    EXIT_CODE=$?

    if [ $EXIT_CODE -gt 1 ]; then
        echo "Retrying..."
        sleep 3
    else
        break
    fi
done

exit $EXIT_CODE