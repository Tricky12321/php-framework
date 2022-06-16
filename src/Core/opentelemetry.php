<?php

namespace Framework\Core;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\Contrib\OtlpHttp\Exporter as OTLPExporter;
use OpenTelemetry\SDK\Common\Environment\Variables as Env;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\Tracer;
use OpenTelemetry\SDK\Trace\TracerProvider;

class opentelemetry
{
    public static TracerInterface $tracer;
    public static TracerProvider $tracerProvider;
    public static SpanExporterInterface $exporter;
    public static SpanInterface $rootSpan;

    public static function startSpan($name, $startScope = false, ?ScopeInterface &$scope = null): SpanInterface
    {
        $e = new \Exception;
        $trace = $e->getTraceAsString();
        $span = self::$tracer->spanBuilder($name)->startSpan();
        $span->setAttribute("stackTrace", $e->getTraceAsString());
        if ($startScope) {
            $scope = $span->activate();
        }
        return $span;
    }

    public static function startOpentelemetry()
    {
        //self::$exporter = Exporter::fromConnectionString("https://fra-sls-agent-api.saas.appdynamics.com", "php-otel", [
        //    "headers" => ["x-api-key" => "7a7933cb1874fe73f585818bf982fc4c69287e1133f645f177d6aa82b2202829"],
        //    "namespace" => "Taskmanager"
        //]);
        //self::$exporter = ZipkinExporter::fromConnectionString("http://zipkin:9411/api/v2/spans","Taskmanager");
        //self::$exporter = JaegerExporter::fromConnectionString("http://jaeger:9412/api/v2/spans","Taskmanager");
        putenv(Env::OTEL_EXPORTER_OTLP_ENDPOINT.'=http://otel-collector:4318');
        putenv(Env::OTEL_EXPORTER_OTLP_HEADERS.'=service.name='.APPLICATION_NAME);
        putenv(Env::OTEL_SERVICE_NAME.'=Taskmanager');
        putenv(Env::OTEL_RESOURCE_ATTRIBUTES.'=service.name='.APPLICATION_NAME.',service.namespace='.APPLICATION_NAME.',telemetry.sdk.language=php');

        self::$exporter = OTLPExporter::fromConnectionString();
        self::$tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(self::$exporter),
            new AlwaysOnSampler(),
        );

        self::$tracer = self::$tracerProvider->getTracer('io.opentelemetry.contrib.php');
        self::$rootSpan = self::$tracer->spanBuilder('root')->startSpan();
        self::$rootSpan->activate();
    }
}