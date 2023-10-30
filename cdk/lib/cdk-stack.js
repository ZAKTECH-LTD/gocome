"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.CdkStack = void 0;
const aws_cdk_lib_1 = require("aws-cdk-lib");
const aws_iam_1 = require("aws-cdk-lib/aws-iam");
// import { AuthorizationToken } from '@aws-cdk/aws-ecr';
const aws_ecr_assets_1 = require("aws-cdk-lib/aws-ecr-assets");
const aws_ec2_1 = require("aws-cdk-lib/aws-ec2");
const aws_logs_1 = require("aws-cdk-lib/aws-logs");
const aws_ecs_1 = require("aws-cdk-lib/aws-ecs");
const aws_elasticloadbalancingv2_1 = require("aws-cdk-lib/aws-elasticloadbalancingv2");
const aws_ssm_1 = require("aws-cdk-lib/aws-ssm");
const aws_secretsmanager_1 = require("aws-cdk-lib/aws-secretsmanager");
const aws_rds_1 = require("aws-cdk-lib/aws-rds");
const aws_sqs_1 = require("aws-cdk-lib/aws-sqs");
const aws_ecs_patterns_1 = require("aws-cdk-lib/aws-ecs-patterns");
const aws_elasticache_1 = require("aws-cdk-lib/aws-elasticache");
const aws_globalaccelerator_1 = require("aws-cdk-lib/aws-globalaccelerator");
const aws_globalaccelerator_endpoints_1 = require("aws-cdk-lib/aws-globalaccelerator-endpoints");
class CdkStack extends aws_cdk_lib_1.Stack {
    constructor(scope, id, props) {
        super(scope, id, props);
        // The code that defines your stack goes here
        const user = new aws_iam_1.User(this, 'deployment-user', {});
        // AuthorizationToken.grantRead(user);
        const applicationImage = new aws_ecr_assets_1.DockerImageAsset(this, 'applicationImage', {
            directory: '..',
            file: './docker/apache/Dockerfile'
        });
        const schedulerImage = new aws_ecr_assets_1.DockerImageAsset(this, 'schedulerImage', {
            directory: '..',
            file: './docker/scheduler/Dockerfile'
        });
        const queueWorkerImage = new aws_ecr_assets_1.DockerImageAsset(this, 'queueWorkerImage', {
            directory: '..',
            file: './docker/queue_worker/Dockerfile'
        });
        // VPC
        const SUBNET_APPLICATION = {
            name: 'Application',
            subnetType: aws_ec2_1.SubnetType.PUBLIC
        };
        const SUBNET_BACKGROUND_TASKS = {
            name: 'Background',
            subnetType: aws_ec2_1.SubnetType.PUBLIC
        };
        const SUBNET_ISOLATED = {
            name: 'RDS-Redis',
            subnetType: aws_ec2_1.SubnetType.PRIVATE_ISOLATED
        };
        const vpc = new aws_ec2_1.Vpc(this, 'my-vpc', {
            natGateways: 0,
            subnetConfiguration: [
                SUBNET_APPLICATION,
                SUBNET_BACKGROUND_TASKS,
                SUBNET_ISOLATED,
            ],
            gatewayEndpoints: {
                S3: {
                    service: aws_ec2_1.GatewayVpcEndpointAwsService.S3,
                },
            },
        });
        // VPC - Private Links
        const ecr = vpc.addInterfaceEndpoint('ecr-gateway', {
            service: aws_ec2_1.InterfaceVpcEndpointAwsService.ECR,
        });
        vpc.addInterfaceEndpoint('ecr-docker-gateway', {
            service: aws_ec2_1.InterfaceVpcEndpointAwsService.ECR_DOCKER,
        });
        const ecs = vpc.addInterfaceEndpoint('ecs-gateway', {
            service: aws_ec2_1.InterfaceVpcEndpointAwsService.ECS,
        });
        const ecsAgent = vpc.addInterfaceEndpoint('ecs-agent-gateway', {
            service: aws_ec2_1.InterfaceVpcEndpointAwsService.ECS_AGENT,
        });
        const ecsTelemetry = vpc.addInterfaceEndpoint('ecs-telemetry-gateway', {
            service: aws_ec2_1.InterfaceVpcEndpointAwsService.ECS_TELEMETRY,
        });
        const sqsEndpoint = vpc.addInterfaceEndpoint('sqs-gateway', {
            service: aws_ec2_1.InterfaceVpcEndpointAwsService.SQS,
        });
        // need to add private link for secrets manager
        const sm = vpc.addInterfaceEndpoint('secrets-manager', {
            service: aws_ec2_1.InterfaceVpcEndpointAwsService.SECRETS_MANAGER
        });
        // need to add private link for cloudwatch
        const cw = vpc.addInterfaceEndpoint('cloudwatch', {
            service: aws_ec2_1.InterfaceVpcEndpointAwsService.CLOUDWATCH_LOGS
        });
        // LOAD BALANCER
        const alb = new aws_elasticloadbalancingv2_1.ApplicationLoadBalancer(this, 'application-ALB', {
            http2Enabled: false,
            internetFacing: true,
            loadBalancerName: 'application',
            vpc,
            vpcSubnets: {
                subnetGroupName: SUBNET_APPLICATION.name
            }
        });
        const loadBalancerSecurityGroup = new aws_ec2_1.SecurityGroup(this, 'load-balancer-SG', {
            vpc,
            allowAllOutbound: true,
        });
        alb.addSecurityGroup(loadBalancerSecurityGroup);
        // For HTTPS you need to set up an ACM and reference it here
        const listener = alb.addListener('alb-target-group', {
            open: true,
            port: 80
        });
        // Target group to make resources containers discoverable by the application load balancer
        const targetGroupHttp = new aws_elasticloadbalancingv2_1.ApplicationTargetGroup(this, 'alb-target-group', {
            port: 80,
            protocol: aws_elasticloadbalancingv2_1.ApplicationProtocol.HTTP,
            targetType: aws_elasticloadbalancingv2_1.TargetType.IP,
            vpc,
        });
        // Health check for containers to check they were deployed correctly
        targetGroupHttp.configureHealthCheck({
            path: '/api/health-check',
            protocol: aws_elasticloadbalancingv2_1.Protocol.HTTP,
        });
        // Add target group to listener
        listener.addTargetGroups('alb-listener-target-group', {
            targetGroups: [targetGroupHttp],
        });
        // Fargate Service Things
        const cluster = new aws_ecs_1.Cluster(this, 'application-cluster', {
            clusterName: 'application',
            containerInsights: true,
            vpc,
        });
        const backgroundCluster = new aws_ecs_1.Cluster(this, 'scheduler-cluster', {
            clusterName: 'background-tasks',
            containerInsights: true,
            vpc,
        });
        // LOG GROUPS
        const applicationLogGroup = new aws_logs_1.LogGroup(this, 'application-log-group', {
            logGroupName: 'application',
            removalPolicy: aws_cdk_lib_1.RemovalPolicy.DESTROY,
            retention: 30
        });
        const schedulerLogGroup = new aws_logs_1.LogGroup(this, 'scheduler-log-group', {
            logGroupName: 'scheduler',
            removalPolicy: aws_cdk_lib_1.RemovalPolicy.DESTROY,
            retention: 30
        });
        const queueWorkerLogGroup = new aws_logs_1.LogGroup(this, 'queue-worker-log-group', {
            logGroupName: 'queue-worker',
            removalPolicy: aws_cdk_lib_1.RemovalPolicy.DESTROY,
            retention: 7
        });
        applicationLogGroup.grant(user, 'logs:CreateLogGroup');
        schedulerLogGroup.grant(user, 'logs:CreateLogGroup');
        queueWorkerLogGroup.grant(user, 'logs:CreateLogGroup');
        const taskRole = new aws_iam_1.Role(this, 'fargate-task-role', {
            assumedBy: new aws_iam_1.ServicePrincipal('ecs-tasks.amazonaws.com'),
            roleName: 'application-fargate-task-role',
            description: 'Role that the api task definitions use to run the api code',
        });
        const applicationServiceDefinition = new aws_ecs_1.TaskDefinition(this, 'application-fargate-service-definition', {
            compatibility: aws_ecs_1.Compatibility.EC2_AND_FARGATE,
            cpu: '256',
            family: 'api-task-family',
            memoryMiB: '512',
            taskRole
        });
        const applicationSecurityGroup = new aws_ec2_1.SecurityGroup(this, 'application-SG', {
            vpc,
            description: 'SecurityGroup into which application ECS tasks will be deployed',
            allowAllOutbound: true
        });
        applicationSecurityGroup.connections.allowFrom(loadBalancerSecurityGroup, aws_ec2_1.Port.allTcp(), 'Load Balancer ingress All TCP');
        ecr.connections.allowFrom(applicationSecurityGroup, aws_ec2_1.Port.tcp(443));
        ecs.connections.allowFrom(applicationSecurityGroup, aws_ec2_1.Port.tcp(443));
        ecsAgent.connections.allowFrom(applicationSecurityGroup, aws_ec2_1.Port.tcp(443));
        ecsTelemetry.connections.allowFrom(applicationSecurityGroup, aws_ec2_1.Port.tcp(443));
        sm.connections.allowFrom(applicationSecurityGroup, aws_ec2_1.Port.tcp(443));
        cw.connections.allowFrom(applicationSecurityGroup, aws_ec2_1.Port.tcp(443));
        const backgroundTasksSecurityGroup = new aws_ec2_1.SecurityGroup(this, 'background-task-SG', {
            vpc,
            description: 'SecurityGroup into which scheduler ECS tasks will be deployed',
            allowAllOutbound: true
        });
        ecr.connections.allowFrom(backgroundTasksSecurityGroup, aws_ec2_1.Port.tcp(443));
        ecs.connections.allowFrom(backgroundTasksSecurityGroup, aws_ec2_1.Port.tcp(443));
        ecsAgent.connections.allowFrom(backgroundTasksSecurityGroup, aws_ec2_1.Port.tcp(443));
        ecsTelemetry.connections.allowFrom(backgroundTasksSecurityGroup, aws_ec2_1.Port.tcp(443));
        sm.connections.allowFrom(backgroundTasksSecurityGroup, aws_ec2_1.Port.tcp(443));
        cw.connections.allowFrom(backgroundTasksSecurityGroup, aws_ec2_1.Port.tcp(443));
        const redisSecurityGroup = new aws_ec2_1.SecurityGroup(this, 'redis-SG', {
            vpc,
            description: 'SecurityGroup associated with the ElastiCache Redis Cluster',
            allowAllOutbound: false
        });
        redisSecurityGroup.connections.allowFrom(applicationSecurityGroup, aws_ec2_1.Port.tcp(6379), 'Application ingress 6379');
        redisSecurityGroup.connections.allowFrom(backgroundTasksSecurityGroup, aws_ec2_1.Port.tcp(6379), 'Scheduler ingress 6379');
        // Parameters
        const LOG_LEVEL = new aws_ssm_1.StringParameter(this, 'Parameter', {
            allowedPattern: '.*',
            description: 'Application log level',
            parameterName: 'LOG_LEVEL',
            stringValue: 'debug',
            tier: aws_ssm_1.ParameterTier.STANDARD,
        }).stringValue;
        const APP_URL = aws_ssm_1.StringParameter.fromStringParameterName(this, 'APP_URL', 'APP_URL').stringValue;
        // RDS
        const databaseSecurityGroup = new aws_ec2_1.SecurityGroup(this, 'database-SG', {
            vpc,
            description: 'SecurityGroup associated with the MySQL RDS Instance',
            allowAllOutbound: false
        });
        databaseSecurityGroup.connections.allowFrom(applicationSecurityGroup, aws_ec2_1.Port.tcp(3306));
        databaseSecurityGroup.connections.allowFrom(backgroundTasksSecurityGroup, aws_ec2_1.Port.tcp(3306));
        const db = new aws_rds_1.DatabaseInstance(this, 'primary-db', {
            allocatedStorage: 20,
            autoMinorVersionUpgrade: true,
            allowMajorVersionUpgrade: false,
            databaseName: 'example',
            engine: aws_rds_1.DatabaseInstanceEngine.mysql({
                version: aws_rds_1.MysqlEngineVersion.VER_8_0_21
            }),
            iamAuthentication: true,
            instanceType: aws_ec2_1.InstanceType.of(aws_ec2_1.InstanceClass.BURSTABLE3, aws_ec2_1.InstanceSize.SMALL),
            maxAllocatedStorage: 250,
            multiAz: false,
            securityGroups: [databaseSecurityGroup],
            vpc,
            vpcSubnets: {
                subnetGroupName: SUBNET_ISOLATED.name
            }
        });
        // ELASTICACHE
        const redisSubnetGroup = new aws_elasticache_1.CfnSubnetGroup(this, 'redis-subnet-group', {
            description: 'Redis Subnet Group',
            subnetIds: vpc.isolatedSubnets.map(s => s.subnetId),
            cacheSubnetGroupName: 'RedisSubnetGroup'
        });
        const redis = new aws_elasticache_1.CfnCacheCluster(this, 'redis-cluster', {
            cacheNodeType: 'cache.t3.small',
            cacheSubnetGroupName: redisSubnetGroup.cacheSubnetGroupName,
            clusterName: 'redis-cluster',
            engine: 'redis',
            engineVersion: '6.x',
            numCacheNodes: 1,
            port: 6379,
            vpcSecurityGroupIds: [redisSecurityGroup.securityGroupId]
        });
        redis.node.addDependency(redisSubnetGroup);
        // SECRETS
        const stripe = aws_secretsmanager_1.Secret.fromSecretNameV2(this, 'stripe_keys', 'STRIPE'); // Don't forget to create this manually
        const secrets = {
            DB_DATABASE: aws_ecs_1.Secret.fromSecretsManager(db.secret, 'dbname'),
            DB_USERNAME: aws_ecs_1.Secret.fromSecretsManager(db.secret, 'username'),
            DB_PASSWORD: aws_ecs_1.Secret.fromSecretsManager(db.secret, 'password'),
            STRIPE_KEY: aws_ecs_1.Secret.fromSecretsManager(stripe, 'STRIPE_KEY'),
            STRIPE_SECRET: aws_ecs_1.Secret.fromSecretsManager(stripe, 'STRIPE_SECRET'),
        };
        // This is specific for laravel application used in examples
        const environment = {
            APP_URL,
            LOG_CHANNEL: 'stdout',
            LOG_LEVEL,
            DB_CONNECTION: 'mysql',
            DB_HOST: db.dbInstanceEndpointAddress,
            DB_PORT: db.dbInstanceEndpointPort,
            CACHE_DRIVER: 'redis',
            REDIS_HOST: redis.attrRedisEndpointAddress,
            REDIS_PASSWORD: 'null',
            REDIS_PORT: '6379',
        };
        const applicationContainer = applicationServiceDefinition.addContainer('app-container', {
            cpu: 256,
            environment,
            essential: true,
            image: aws_ecs_1.ContainerImage.fromDockerImageAsset(applicationImage),
            logging: aws_ecs_1.LogDriver.awsLogs({
                logGroup: applicationLogGroup,
                streamPrefix: new Date().toLocaleDateString('en-ZA')
            }),
            memoryLimitMiB: 512,
            secrets,
        });
        applicationContainer.addPortMappings({
            containerPort: 80,
            hostPort: 80,
            protocol: aws_ecs_1.Protocol.TCP
        });
        const applicationService = new aws_ecs_1.FargateService(this, 'application-fargate-service', {
            assignPublicIp: true,
            circuitBreaker: {
                rollback: true
            },
            deploymentController: {
                type: aws_ecs_1.DeploymentControllerType.ECS
            },
            desiredCount: 1,
            cluster,
            platformVersion: aws_ecs_1.FargatePlatformVersion.LATEST,
            securityGroups: [applicationSecurityGroup],
            taskDefinition: applicationServiceDefinition,
            vpcSubnets: {
                subnetGroupName: SUBNET_APPLICATION.name
            }
        });
        applicationService.attachToApplicationTargetGroup(targetGroupHttp);
        const scaleTarget = applicationService.autoScaleTaskCount({
            minCapacity: 1,
            maxCapacity: 10,
        });
        scaleTarget.scaleOnMemoryUtilization('scale-out-memory-threshold', {
            targetUtilizationPercent: 75
        });
        scaleTarget.scaleOnCpuUtilization('scale-out-cpu-threshold', {
            targetUtilizationPercent: 75
        });
        // Scheduled Tasks
        const scheduledServiceRole = new aws_iam_1.Role(this, 'scheduled-fargate-task-role', {
            assumedBy: new aws_iam_1.ServicePrincipal('ecs-tasks.amazonaws.com'),
            roleName: 'scheduled-fargate-task-role',
            description: 'Role that the scheduled task definitions use to run scheduled jobs',
        });
        const scheduledServiceDefinition = new aws_ecs_1.TaskDefinition(this, 'background-fargate-service-definition', {
            compatibility: aws_ecs_1.Compatibility.EC2_AND_FARGATE,
            cpu: '256',
            family: 'background-task-family',
            memoryMiB: '512',
            taskRole: scheduledServiceRole
        });
        // We don't want to autoscale scheduled tasks. Otherwise each container will run each job independently
        // If scheduled jobs are slow running you are better off pushing the work to the queue
        const scheduledService = new aws_ecs_1.FargateService(this, 'scheduled-fargate-service', {
            assignPublicIp: true,
            circuitBreaker: {
                rollback: true
            },
            deploymentController: {
                type: aws_ecs_1.DeploymentControllerType.ECS
            },
            desiredCount: 1,
            cluster: backgroundCluster,
            platformVersion: aws_ecs_1.FargatePlatformVersion.LATEST,
            securityGroups: [backgroundTasksSecurityGroup],
            taskDefinition: scheduledServiceDefinition,
            vpcSubnets: {
                subnetGroupName: SUBNET_BACKGROUND_TASKS.name
            }
        });
        scheduledService.taskDefinition.addContainer('background-container', {
            cpu: 256,
            environment,
            essential: true,
            image: aws_ecs_1.ContainerImage.fromDockerImageAsset(schedulerImage),
            logging: aws_ecs_1.LogDriver.awsLogs({
                logGroup: schedulerLogGroup,
                streamPrefix: new Date().toLocaleDateString('en-ZA'),
            }),
            memoryLimitMiB: 512,
            secrets,
        });
        // SQS and QueueProcessingService
        const schedulerJobQueue = new aws_sqs_1.Queue(this, 'job-queue', {
            queueName: 'scheduler-job-queue'
        });
        const sqsPolicy = new aws_iam_1.Policy(this, 'fargate-task-sqs-policy', {
            statements: [
                new aws_iam_1.PolicyStatement({
                    effect: aws_iam_1.Effect.ALLOW,
                    actions: ['sqs:*'],
                    resources: [schedulerJobQueue.queueArn],
                }),
            ],
        });
        const queueWorkerService = new aws_ecs_patterns_1.QueueProcessingFargateService(this, 'queued-jobs', {
            assignPublicIp: false,
            circuitBreaker: {
                rollback: true
            },
            cluster: backgroundCluster,
            cpu: 256,
            deploymentController: {
                type: aws_ecs_1.DeploymentControllerType.ECS
            },
            enableLogging: true,
            environment,
            image: aws_ecs_1.ContainerImage.fromDockerImageAsset(queueWorkerImage),
            logDriver: aws_ecs_1.LogDriver.awsLogs({
                logGroup: queueWorkerLogGroup,
                streamPrefix: new Date().toLocaleDateString('en-ZA')
            }),
            maxScalingCapacity: 2,
            memoryLimitMiB: 512,
            queue: schedulerJobQueue,
            secrets,
            platformVersion: aws_ecs_1.FargatePlatformVersion.LATEST,
            securityGroups: [backgroundTasksSecurityGroup],
            taskSubnets: {
                subnetGroupName: SUBNET_BACKGROUND_TASKS.name
            }
        });
        // Allow ECS to grab the images to spin up new containers
        applicationImage.repository.grantPull(applicationService.taskDefinition.obtainExecutionRole());
        schedulerImage.repository.grantPull(scheduledService.taskDefinition.obtainExecutionRole());
        queueWorkerImage.repository.grantPull(queueWorkerService.taskDefinition.obtainExecutionRole());
        // SQS Permissions
        sqsEndpoint.connections.allowFrom(backgroundTasksSecurityGroup, aws_ec2_1.Port.tcp(443));
        sqsEndpoint.connections.allowFrom(applicationSecurityGroup, aws_ec2_1.Port.tcp(443));
        // Application Permissions grants
        taskRole.attachInlinePolicy(sqsPolicy);
        scheduledServiceRole.attachInlinePolicy(sqsPolicy);
        queueWorkerService.taskDefinition.taskRole.attachInlinePolicy(sqsPolicy);
        schedulerJobQueue.grantSendMessages(applicationService.taskDefinition.obtainExecutionRole());
        schedulerJobQueue.grantSendMessages(scheduledService.taskDefinition.obtainExecutionRole());
        schedulerJobQueue.grantSendMessages(queueWorkerService.taskDefinition.obtainExecutionRole());
        schedulerJobQueue.grantPurge(queueWorkerService.taskDefinition.obtainExecutionRole());
        schedulerJobQueue.grantConsumeMessages(queueWorkerService.taskDefinition.obtainExecutionRole());
        // SECRETS PERMISSIONS
        Object.values(secrets).forEach(secret => {
            secret.grantRead(applicationService.taskDefinition.obtainExecutionRole());
            secret.grantRead(scheduledService.taskDefinition.obtainExecutionRole());
            secret.grantRead(queueWorkerService.taskDefinition.obtainExecutionRole());
        });
        // Log Permissions
        applicationLogGroup.grant(applicationService.taskDefinition.obtainExecutionRole(), 'logs:CreateLogStream');
        applicationLogGroup.grant(applicationService.taskDefinition.obtainExecutionRole(), 'logs:PutLogEvents');
        schedulerLogGroup.grant(scheduledService.taskDefinition.obtainExecutionRole(), 'logs:CreateLogStream');
        schedulerLogGroup.grant(scheduledService.taskDefinition.obtainExecutionRole(), 'logs:PutLogEvents');
        queueWorkerLogGroup.grant(queueWorkerService.taskDefinition.obtainExecutionRole(), 'logs:CreateLogStream');
        queueWorkerLogGroup.grant(queueWorkerService.taskDefinition.obtainExecutionRole(), 'logs:PutLogEvents');
        // DB permissions
        db.grantConnect(applicationService.taskDefinition.taskRole);
        db.grantConnect(scheduledService.taskDefinition.taskRole);
        db.grantConnect(queueWorkerService.taskDefinition.taskRole);
        // Create an Accelerator
        const accelerator = new aws_globalaccelerator_1.Accelerator(this, 'global-accelerator');
        // Create a Listener
        const acceleratorListener = accelerator.addListener('global-accelerator-listener', {
            portRanges: [
                { fromPort: 80 },
                { fromPort: 443 },
            ],
        });
        const endpointGroup = acceleratorListener.addEndpointGroup('global-accelerator-listener-alb-group', {
            endpoints: [
                new aws_globalaccelerator_endpoints_1.ApplicationLoadBalancerEndpoint(alb, {
                    preserveClientIp: true,
                })
            ],
            healthCheckInterval: aws_cdk_lib_1.Duration.seconds(30),
            healthCheckPath: '/api/health-check'
        });
        // Remember that there is only one AGA security group per VPC.
        const acceleratorSecurityGroup = endpointGroup.connectionsPeer('GlobalAcceleratorSG', vpc);
        // Allow connections from the AGA to the ALB
        alb.connections.allowFrom(acceleratorSecurityGroup, aws_ec2_1.Port.tcp(443));
    }
}
exports.CdkStack = CdkStack;
//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiY2RrLXN0YWNrLmpzIiwic291cmNlUm9vdCI6IiIsInNvdXJjZXMiOlsiY2RrLXN0YWNrLnRzIl0sIm5hbWVzIjpbXSwibWFwcGluZ3MiOiI7OztBQUFBLDZDQUF5RTtBQUN6RSxpREFBb0c7QUFDcEcseURBQXlEO0FBQ3pELCtEQUE4RDtBQUM5RCxpREFRNkI7QUFDN0IsbURBQWdEO0FBRWhELGlEQVc2QjtBQUM3Qix1RkFBb0o7QUFDcEosaURBQXFFO0FBQ3JFLHVFQUF5RTtBQUN6RSxpREFBbUc7QUFDbkcsaURBQTRDO0FBQzVDLG1FQUE2RTtBQUM3RSxpRUFBOEU7QUFDOUUsNkVBQWdFO0FBQ2hFLGlHQUE4RjtBQUU5RixNQUFhLFFBQVMsU0FBUSxtQkFBSztJQUNqQyxZQUFZLEtBQWdCLEVBQUUsRUFBVSxFQUFFLEtBQWtCO1FBQzFELEtBQUssQ0FBQyxLQUFLLEVBQUUsRUFBRSxFQUFFLEtBQUssQ0FBQyxDQUFDO1FBRXhCLDZDQUE2QztRQUM3QyxNQUFNLElBQUksR0FBRyxJQUFJLGNBQUksQ0FBQyxJQUFJLEVBQUUsaUJBQWlCLEVBQUUsRUFBRSxDQUFDLENBQUM7UUFDbkQsc0NBQXNDO1FBRXRDLE1BQU0sZ0JBQWdCLEdBQUcsSUFBSSxpQ0FBZ0IsQ0FBQyxJQUFJLEVBQUUsa0JBQWtCLEVBQUU7WUFDdEUsU0FBUyxFQUFFLElBQUk7WUFDZixJQUFJLEVBQUUsNEJBQTRCO1NBQ25DLENBQUMsQ0FBQztRQUVILE1BQU0sY0FBYyxHQUFHLElBQUksaUNBQWdCLENBQUMsSUFBSSxFQUFFLGdCQUFnQixFQUFFO1lBQ2xFLFNBQVMsRUFBRSxJQUFJO1lBQ2YsSUFBSSxFQUFFLCtCQUErQjtTQUN0QyxDQUFDLENBQUM7UUFFSCxNQUFNLGdCQUFnQixHQUFHLElBQUksaUNBQWdCLENBQUMsSUFBSSxFQUFFLGtCQUFrQixFQUFFO1lBQ3RFLFNBQVMsRUFBRSxJQUFJO1lBQ2YsSUFBSSxFQUFFLGtDQUFrQztTQUN6QyxDQUFDLENBQUM7UUFFSCxNQUFNO1FBQ04s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b250ZW50IjpbImltcG9ydCB7IER1cmF0aW9uLCBSZW1vdmFsUG9saWN5LCBTdGFjaywgU3RhY2tQcm9wcyB9IGZyb20gJ2F3cy1jZGstbGliJztcbmltcG9ydCB7IEVmZmVjdCwgUG9saWN5LCBQb2xpY3lTdGF0ZW1lbnQsIFJvbGUsIFNlcnZpY2VQcmluY2lwYWwsIFVzZXIgfSBmcm9tICdhd3MtY2RrLWxpYi9hd3MtaWFtJztcbi8vIGltcG9ydCB7IEF1dGhvcml6YXRpb25Ub2tlbiB9IGZyb20gJ0Bhd3MtY2RrL2F3cy1lY3InO1xuaW1wb3J0IHsgRG9ja2VySW1hZ2VBc3NldCB9IGZyb20gJ2F3cy1jZGstbGliL2F3cy1lY3ItYXNzZXRzJztcbmltcG9ydCB7XG4gIEdhdGV3YXlWcGNFbmRwb2ludEF3c1NlcnZpY2UsIEluc3RhbmNlQ2xhc3MsIEluc3RhbmNlU2l6ZSxcbiAgSW5zdGFuY2VUeXBlLFxuICBJbnRlcmZhY2VWcGNFbmRwb2ludEF3c1NlcnZpY2UsXG4gIFBvcnQsXG4gIFNlY3VyaXR5R3JvdXAsXG4gIFN1Ym5ldFR5cGUsXG4gIFZwY1xufSBmcm9tICdhd3MtY2RrLWxpYi9hd3MtZWMyJztcbmltcG9ydCB7IExvZ0dyb3VwIH0gZnJvbSAnYXdzLWNkay1saWIvYXdzLWxvZ3MnO1xuaW1wb3J0IHsgQ29uc3RydWN0IH0gZnJvbSAnY29uc3RydWN0cyc7XG5pbXBvcnQge1xuICBDbHVzdGVyLFxuICBDb21wYXRpYmlsaXR5LFxuICBDb250YWluZXJJbWFnZSxcbiAgRGVwbG95bWVudENvbnRyb2xsZXJUeXBlLFxuICBGYXJnYXRlUGxhdGZvcm1WZXJzaW9uLFxuICBGYXJnYXRlU2VydmljZSxcbiAgU2VjcmV0LFxuICBUYXNrRGVmaW5pdGlvbixcbiAgUHJvdG9jb2wgYXMgRWNzUHJvdG9jb2wsXG4gIExvZ0RyaXZlclxufSBmcm9tICdhd3MtY2RrLWxpYi9hd3MtZWNzJztcbmltcG9ydCB7IEFwcGxpY2F0aW9uTG9hZEJhbGFuY2VyLCBBcHBsaWNhdGlvblByb3RvY29sLCBBcHBsaWNhdGlvblRhcmdldEdyb3VwLCBQcm90b2NvbCwgVGFyZ2V0VHlwZSB9IGZyb20gJ2F3cy1jZGstbGliL2F3cy1lbGFzdGljbG9hZGJhbGFuY2luZ3YyJztcbmltcG9ydCB7IFBhcmFtZXRlclRpZXIsIFN0cmluZ1BhcmFtZXRlciB9IGZyb20gJ2F3cy1jZGstbGliL2F3cy1zc20nO1xuaW1wb3J0IHsgU2VjcmV0IGFzIFNlY3JldE1hbmFnZXIgfSBmcm9tICdhd3MtY2RrLWxpYi9hd3Mtc2VjcmV0c21hbmFnZXInO1xuaW1wb3J0IHsgRGF0YWJhc2VJbnN0YW5jZSwgRGF0YWJhc2VJbnN0YW5jZUVuZ2luZSwgTXlzcWxFbmdpbmVWZXJzaW9uIH0gZnJvbSAnYXdzLWNkay1saWIvYXdzLXJkcyc7XG5pbXBvcnQgeyBRdWV1ZSB9IGZyb20gJ2F3cy1jZGstbGliL2F3cy1zcXMnO1xuaW1wb3J0IHsgUXVldWVQcm9jZXNzaW5nRmFyZ2F0ZVNlcnZpY2UgfSBmcm9tICdhd3MtY2RrLWxpYi9hd3MtZWNzLXBhdHRlcm5zJztcbmltcG9ydCB7IENmbkNhY2hlQ2x1c3RlciwgQ2ZuU3VibmV0R3JvdXAgfSBmcm9tICdhd3MtY2RrLWxpYi9hd3MtZWxhc3RpY2FjaGUnO1xuaW1wb3J0IHsgQWNjZWxlcmF0b3IgfSBmcm9tICdhd3MtY2RrLWxpYi9hd3MtZ2xvYmFsYWNjZWxlcmF0b3InO1xuaW1wb3J0IHsgQXBwbGljYXRpb25Mb2FkQmFsYW5jZXJFbmRwb2ludCB9IGZyb20gJ2F3cy1jZGstbGliL2F3cy1nbG9iYWxhY2NlbGVyYXRvci1lbmRwb2ludHMnO1xuXG5leHBvcnQgY2xhc3MgQ2RrU3RhY2sgZXh0ZW5kcyBTdGFjayB7XG4gIGNvbnN0cnVjdG9yKHNjb3BlOiBDb25zdHJ1Y3QsIGlkOiBzdHJpbmcsIHByb3BzPzogU3RhY2tQcm9wcykge1xuICAgIHN1cGVyKHNjb3BlLCBpZCwgcHJvcHMpO1xuXG4gICAgLy8gVGhlIGNvZGUgdGhhdCBkZWZpbmVzIHlvdXIgc3RhY2sgZ29lcyBoZXJlXG4gICAgY29uc3QgdXNlciA9IG5ldyBVc2VyKHRoaXMsICdkZXBsb3ltZW50LXVzZXInLCB7fSk7XG4gICAgLy8gQXV0aG9yaXphdGlvblRva2VuLmdyYW50UmVhZCh1c2VyKTtcblxuICAgIGNvbnN0IGFwcGxpY2F0aW9uSW1hZ2UgPSBuZXcgRG9ja2VySW1hZ2VBc3NldCh0aGlzLCAnYXBwbGljYXRpb25JbWFnZScsIHtcbiAgICAgIGRpcmVjdG9yeTogJy4uJyxcbiAgICAgIGZpbGU6ICcuL2RvY2tlci9hcGFjaGUvRG9ja2VyZmlsZSdcbiAgICB9KTtcblxuICAgIGNvbnN0IHNjaGVkdWxlckltYWdlID0gbmV3IERvY2tlckltYWdlQXNzZXQodGhpcywgJ3NjaGVkdWxlckltYWdlJywge1xuICAgICAgZGlyZWN0b3J5OiAnLi4nLFxuICAgICAgZmlsZTogJy4vZG9ja2VyL3NjaGVkdWxlci9Eb2NrZXJmaWxlJ1xuICAgIH0pO1xuXG4gICAgY29uc3QgcXVldWVXb3JrZXJJbWFnZSA9IG5ldyBEb2NrZXJJbWFnZUFzc2V0KHRoaXMsICdxdWV1ZVdvcmtlckltYWdlJywge1xuICAgICAgZGlyZWN0b3J5OiAnLi4nLFxuICAgICAgZmlsZTogJy4vZG9ja2VyL3F1ZXVlX3dvcmtlci9Eb2NrZXJmaWxlJ1xuICAgIH0pO1xuXG4gICAgLy8gVlBDXG4gICAgY29uc3QgU1VCTkVUX0FQUExJQ0FUSU9OID0ge1xuICAgICAgbmFtZTogJ0FwcGxpY2F0aW9uJyxcbiAgICAgIHN1Ym5ldFR5cGU6IFN1Ym5ldFR5cGUuUFVCTElDXG4gICAgfTtcblxuICAgIGNvbnN0IFNVQk5FVF9CQUNLR1JPVU5EX1RBU0tTID0ge1xuICAgICAgbmFtZTogJ0JhY2tncm91bmQnLFxuICAgICAgc3VibmV0VHlwZTogU3VibmV0VHlwZS5QVUJMSUNcbiAgICB9O1xuXG4gICAgY29uc3QgU1VCTkVUX0lTT0xBVEVEID0ge1xuICAgICAgbmFtZTogJ1JEUy1SZWRpcycsXG4gICAgICBzdWJuZXRUeXBlOiBTdWJuZXRUeXBlLlBSSVZBVEVfSVNPTEFURURcbiAgICB9O1xuXG4gICAgY29uc3QgdnBjID0gbmV3IFZwYyh0aGlzLCAnbXktdnBjJywge1xuICAgICAgbmF0R2F0ZXdheXM6IDAsXG4gICAgICBzdWJuZXRDb25maWd1cmF0aW9uOiBbXG4gICAgICAgIFNVQk5FVF9BUFBMSUNBVElPTixcbiAgICAgICAgU1VCTkVUX0JBQ0tHUk9VTkRfVEFTS1MsXG4gICAgICAgIFNVQk5FVF9JU09MQVRFRCxcbiAgICAgIF0sXG4gICAgICBnYXRld2F5RW5kcG9pbnRzOiB7XG4gICAgICAgIFMzOiB7XG4gICAgICAgICAgc2VydmljZTogR2F0ZXdheVZwY0VuZHBvaW50QXdzU2VydmljZS5TMyxcbiAgICAgICAgfSxcbiAgICAgIH0sXG4gICAgfSk7XG5cbiAgICAvLyBWUEMgLSBQcml2YXRlIExpbmtzXG4gICAgY29uc3QgZWNyID0gdnBjLmFkZEludGVyZmFjZUVuZHBvaW50KCdlY3ItZ2F0ZXdheScsIHtcbiAgICAgIHNlcnZpY2U6IEludGVyZmFjZVZwY0VuZHBvaW50QXdzU2VydmljZS5FQ1IsXG4gICAgfSk7XG5cbiAgICB2cGMuYWRkSW50ZXJmYWNlRW5kcG9pbnQoJ2Vjci1kb2NrZXItZ2F0ZXdheScsIHtcbiAgICAgIHNlcnZpY2U6IEludGVyZmFjZVZwY0VuZHBvaW50QXdzU2VydmljZS5FQ1JfRE9DS0VSLFxuICAgIH0pO1xuXG4gICAgY29uc3QgZWNzID0gdnBjLmFkZEludGVyZmFjZUVuZHBvaW50KCdlY3MtZ2F0ZXdheScsIHtcbiAgICAgIHNlcnZpY2U6IEludGVyZmFjZVZwY0VuZHBvaW50QXdzU2VydmljZS5FQ1MsXG4gICAgfSk7XG5cbiAgICBjb25zdCBlY3NBZ2VudCA9IHZwYy5hZGRJbnRlcmZhY2VFbmRwb2ludCgnZWNzLWFnZW50LWdhdGV3YXknLCB7XG4gICAgICBzZXJ2aWNlOiBJbnRlcmZhY2VWcGNFbmRwb2ludEF3c1NlcnZpY2UuRUNTX0FHRU5ULFxuICAgIH0pO1xuXG4gICAgY29uc3QgZWNzVGVsZW1ldHJ5ID0gdnBjLmFkZEludGVyZmFjZUVuZHBvaW50KCdlY3MtdGVsZW1ldHJ5LWdhdGV3YXknLCB7XG4gICAgICBzZXJ2aWNlOiBJbnRlcmZhY2VWcGNFbmRwb2ludEF3c1NlcnZpY2UuRUNTX1RFTEVNRVRSWSxcbiAgICB9KTtcblxuICAgIGNvbnN0IHNxc0VuZHBvaW50ID0gdnBjLmFkZEludGVyZmFjZUVuZHBvaW50KCdzcXMtZ2F0ZXdheScsIHtcbiAgICAgIHNlcnZpY2U6IEludGVyZmFjZVZwY0VuZHBvaW50QXdzU2VydmljZS5TUVMsXG4gICAgfSk7XG5cbiAgICAvLyBuZWVkIHRvIGFkZCBwcml2YXRlIGxpbmsgZm9yIHNlY3JldHMgbWFuYWdlclxuICAgIGNvbnN0IHNtID0gdnBjLmFkZEludGVyZmFjZUVuZHBvaW50KCdzZWNyZXRzLW1hbmFnZXInLCB7XG4gICAgICBzZXJ2aWNlOiBJbnRlcmZhY2VWcGNFbmRwb2ludEF3c1NlcnZpY2UuU0VDUkVUU19NQU5BR0VSXG4gICAgfSk7XG5cbiAgICAvLyBuZWVkIHRvIGFkZCBwcml2YXRlIGxpbmsgZm9yIGNsb3Vkd2F0Y2hcbiAgICBjb25zdCBjdyA9IHZwYy5hZGRJbnRlcmZhY2VFbmRwb2ludCgnY2xvdWR3YXRjaCcsIHtcbiAgICAgIHNlcnZpY2U6IEludGVyZmFjZVZwY0VuZHBvaW50QXdzU2VydmljZS5DTE9VRFdBVENIX0xPR1NcbiAgICB9KTtcblxuICAgIC8vIExPQUQgQkFMQU5DRVJcbiAgICBjb25zdCBhbGIgPSBuZXcgQXBwbGljYXRpb25Mb2FkQmFsYW5jZXIodGhpcywgJ2FwcGxpY2F0aW9uLUFMQicsIHtcbiAgICAgIGh0dHAyRW5hYmxlZDogZmFsc2UsXG4gICAgICBpbnRlcm5ldEZhY2luZzogdHJ1ZSxcbiAgICAgIGxvYWRCYWxhbmNlck5hbWU6ICdhcHBsaWNhdGlvbicsXG4gICAgICB2cGMsXG4gICAgICB2cGNTdWJuZXRzOiB7XG4gICAgICAgIHN1Ym5ldEdyb3VwTmFtZTogU1VCTkVUX0FQUExJQ0FUSU9OLm5hbWVcbiAgICAgIH1cbiAgICB9KTtcblxuICAgIGNvbnN0IGxvYWRCYWxhbmNlclNlY3VyaXR5R3JvdXAgPSBuZXcgU2VjdXJpdHlHcm91cCh0aGlzLCAnbG9hZC1iYWxhbmNlci1TRycsIHtcbiAgICAgIHZwYyxcbiAgICAgIGFsbG93QWxsT3V0Ym91bmQ6IHRydWUsXG4gICAgfSk7XG5cbiAgICBhbGIuYWRkU2VjdXJpdHlHcm91cChsb2FkQmFsYW5jZXJTZWN1cml0eUdyb3VwKTtcblxuICAgIC8vIEZvciBIVFRQUyB5b3UgbmVlZCB0byBzZXQgdXAgYW4gQUNNIGFuZCByZWZlcmVuY2UgaXQgaGVyZVxuICAgIGNvbnN0IGxpc3RlbmVyID0gYWxiLmFkZExpc3RlbmVyKCdhbGItdGFyZ2V0LWdyb3VwJywge1xuICAgICAgb3BlbjogdHJ1ZSxcbiAgICAgIHBvcnQ6IDgwXG4gICAgfSk7XG5cbiAgICAvLyBUYXJnZXQgZ3JvdXAgdG8gbWFrZSByZXNvdXJjZXMgY29udGFpbmVycyBkaXNjb3ZlcmFibGUgYnkgdGhlIGFwcGxpY2F0aW9uIGxvYWQgYmFsYW5jZXJcbiAgICBjb25zdCB0YXJnZXRHcm91cEh0dHAgPSBuZXcgQXBwbGljYXRpb25UYXJnZXRHcm91cCh0aGlzLCAnYWxiLXRhcmdldC1ncm91cCcsIHtcbiAgICAgIHBvcnQ6IDgwLFxuICAgICAgcHJvdG9jb2w6IEFwcGxpY2F0aW9uUHJvdG9jb2wuSFRUUCxcbiAgICAgIHRhcmdldFR5cGU6IFRhcmdldFR5cGUuSVAsXG4gICAgICB2cGMsXG4gICAgfSk7XG4gICAgLy8gSGVhbHRoIGNoZWNrIGZvciBjb250YWluZXJzIHRvIGNoZWNrIHRoZXkgd2VyZSBkZXBsb3llZCBjb3JyZWN0bHlcbiAgICB0YXJnZXRHcm91cEh0dHAuY29uZmlndXJlSGVhbHRoQ2hlY2soe1xuICAgICAgcGF0aDogJy9hcGkvaGVhbHRoLWNoZWNrJyxcbiAgICAgIHByb3RvY29sOiBQcm90b2NvbC5IVFRQLFxuICAgIH0pO1xuICAgIC8vIEFkZCB0YXJnZXQgZ3JvdXAgdG8gbGlzdGVuZXJcbiAgICBsaXN0ZW5lci5hZGRUYXJnZXRHcm91cHMoJ2FsYi1saXN0ZW5lci10YXJnZXQtZ3JvdXAnLCB7XG4gICAgICB0YXJnZXRHcm91cHM6IFt0YXJnZXRHcm91cEh0dHBdLFxuICAgIH0pO1xuXG4gICAgLy8gRmFyZ2F0ZSBTZXJ2aWNlIFRoaW5nc1xuICAgIGNvbnN0IGNsdXN0ZXIgPSBuZXcgQ2x1c3Rlcih0aGlzLCAnYXBwbGljYXRpb24tY2x1c3RlcicsIHtcbiAgICAgIGNsdXN0ZXJOYW1lOiAnYXBwbGljYXRpb24nLFxuICAgICAgY29udGFpbmVySW5zaWdodHM6IHRydWUsXG4gICAgICB2cGMsXG4gICAgfSk7XG5cbiAgICBjb25zdCBiYWNrZ3JvdW5kQ2x1c3RlciA9IG5ldyBDbHVzdGVyKHRoaXMsICdzY2hlZHVsZXItY2x1c3RlcicsIHtcbiAgICAgIGNsdXN0ZXJOYW1lOiAnYmFja2dyb3VuZC10YXNrcycsXG4gICAgICBjb250YWluZXJJbnNpZ2h0czogdHJ1ZSxcbiAgICAgIHZwYyxcbiAgICB9KTtcblxuICAgIC8vIExPRyBHUk9VUFNcbiAgICBjb25zdCBhcHBsaWNhdGlvbkxvZ0dyb3VwID0gbmV3IExvZ0dyb3VwKHRoaXMsICdhcHBsaWNhdGlvbi1sb2ctZ3JvdXAnLCB7XG4gICAgICBsb2dHcm91cE5hbWU6ICdhcHBsaWNhdGlvbicsXG4gICAgICByZW1vdmFsUG9saWN5OiBSZW1vdmFsUG9saWN5LkRFU1RST1ksXG4gICAgICByZXRlbnRpb246IDMwXG4gICAgfSk7XG4gICAgY29uc3Qgc2NoZWR1bGVyTG9nR3JvdXAgPSBuZXcgTG9nR3JvdXAodGhpcywgJ3NjaGVkdWxlci1sb2ctZ3JvdXAnLCB7XG4gICAgICBsb2dHcm91cE5hbWU6ICdzY2hlZHVsZXInLFxuICAgICAgcmVtb3ZhbFBvbGljeTogUmVtb3ZhbFBvbGljeS5ERVNUUk9ZLFxuICAgICAgcmV0ZW50aW9uOiAzMFxuICAgIH0pO1xuICAgIGNvbnN0IHF1ZXVlV29ya2VyTG9nR3JvdXAgPSBuZXcgTG9nR3JvdXAodGhpcywgJ3F1ZXVlLXdvcmtlci1sb2ctZ3JvdXAnLCB7XG4gICAgICBsb2dHcm91cE5hbWU6ICdxdWV1ZS13b3JrZXInLFxuICAgICAgcmVtb3ZhbFBvbGljeTogUmVtb3ZhbFBvbGljeS5ERVNUUk9ZLFxuICAgICAgcmV0ZW50aW9uOiA3XG4gICAgfSk7XG5cbiAgICBhcHBsaWNhdGlvbkxvZ0dyb3VwLmdyYW50KHVzZXIsICdsb2dzOkNyZWF0ZUxvZ0dyb3VwJyk7XG4gICAgc2NoZWR1bGVyTG9nR3JvdXAuZ3JhbnQodXNlciwgJ2xvZ3M6Q3JlYXRlTG9nR3JvdXAnKTtcbiAgICBxdWV1ZVdvcmtlckxvZ0dyb3VwLmdyYW50KHVzZXIsICdsb2dzOkNyZWF0ZUxvZ0dyb3VwJyk7XG5cbiAgICBjb25zdCB0YXNrUm9sZSA9IG5ldyBSb2xlKHRoaXMsICdmYXJnYXRlLXRhc2stcm9sZScsIHtcbiAgICAgIGFzc3VtZWRCeTogbmV3IFNlcnZpY2VQcmluY2lwYWwoJ2Vjcy10YXNrcy5hbWF6b25hd3MuY29tJyksXG4gICAgICByb2xlTmFtZTogJ2FwcGxpY2F0aW9uLWZhcmdhdGUtdGFzay1yb2xlJyxcbiAgICAgIGRlc2NyaXB0aW9uOiAnUm9sZSB0aGF0IHRoZSBhcGkgdGFzayBkZWZpbml0aW9ucyB1c2UgdG8gcnVuIHRoZSBhcGkgY29kZScsXG4gICAgfSk7XG5cbiAgICBjb25zdCBhcHBsaWNhdGlvblNlcnZpY2VEZWZpbml0aW9uID0gbmV3IFRhc2tEZWZpbml0aW9uKHRoaXMsICdhcHBsaWNhdGlvbi1mYXJnYXRlLXNlcnZpY2UtZGVmaW5pdGlvbicsIHtcbiAgICAgIGNvbXBhdGliaWxpdHk6IENvbXBhdGliaWxpdHkuRUMyX0FORF9GQVJHQVRFLFxuICAgICAgY3B1OiAnMjU2JyxcbiAgICAgIGZhbWlseTogJ2FwaS10YXNrLWZhbWlseScsXG4gICAgICBtZW1vcnlNaUI6ICc1MTInLFxuICAgICAgdGFza1JvbGVcbiAgICB9KTtcblxuICAgIGNvbnN0IGFwcGxpY2F0aW9uU2VjdXJpdHlHcm91cCA9IG5ldyBTZWN1cml0eUdyb3VwKHRoaXMsICdhcHBsaWNhdGlvbi1TRycsIHtcbiAgICAgIHZwYyxcbiAgICAgIGRlc2NyaXB0aW9uOiAnU2VjdXJpdHlHcm91cCBpbnRvIHdoaWNoIGFwcGxpY2F0aW9uIEVDUyB0YXNrcyB3aWxsIGJlIGRlcGxveWVkJyxcbiAgICAgIGFsbG93QWxsT3V0Ym91bmQ6IHRydWVcbiAgICB9KTtcbiAgICBhcHBsaWNhdGlvblNlY3VyaXR5R3JvdXAuY29ubmVjdGlvbnMuYWxsb3dGcm9tKGxvYWRCYWxhbmNlclNlY3VyaXR5R3JvdXAsIFBvcnQuYWxsVGNwKCksICdMb2FkIEJhbGFuY2VyIGluZ3Jlc3MgQWxsIFRDUCcpO1xuICAgIGVjci5jb25uZWN0aW9ucy5hbGxvd0Zyb20oYXBwbGljYXRpb25TZWN1cml0eUdyb3VwLCBQb3J0LnRjcCg0NDMpKTtcbiAgICBlY3MuY29ubmVjdGlvbnMuYWxsb3dGcm9tKGFwcGxpY2F0aW9uU2VjdXJpdHlHcm91cCwgUG9ydC50Y3AoNDQzKSk7XG4gICAgZWNzQWdlbnQuY29ubmVjdGlvbnMuYWxsb3dGcm9tKGFwcGxpY2F0aW9uU2VjdXJpdHlHcm91cCwgUG9ydC50Y3AoNDQzKSk7XG4gICAgZWNzVGVsZW1ldHJ5LmNvbm5lY3Rpb25zLmFsbG93RnJvbShhcHBsaWNhdGlvblNlY3VyaXR5R3JvdXAsIFBvcnQudGNwKDQ0MykpO1xuICAgIHNtLmNvbm5lY3Rpb25zLmFsbG93RnJvbShhcHBsaWNhdGlvblNlY3VyaXR5R3JvdXAsIFBvcnQudGNwKDQ0MykpO1xuICAgIGN3LmNvbm5lY3Rpb25zLmFsbG93RnJvbShhcHBsaWNhdGlvblNlY3VyaXR5R3JvdXAsIFBvcnQudGNwKDQ0MykpO1xuXG4gICAgY29uc3QgYmFja2dyb3VuZFRhc2tzU2VjdXJpdHlHcm91cCA9IG5ldyBTZWN1cml0eUdyb3VwKHRoaXMsICdiYWNrZ3JvdW5kLXRhc2stU0cnLCB7XG4gICAgICB2cGMsXG4gICAgICBkZXNjcmlwdGlvbjogJ1NlY3VyaXR5R3JvdXAgaW50byB3aGljaCBzY2hlZHVsZXIgRUNTIHRhc2tzIHdpbGwgYmUgZGVwbG95ZWQnLFxuICAgICAgYWxsb3dBbGxPdXRib3VuZDogdHJ1ZVxuICAgIH0pO1xuICAgIGVjci5jb25uZWN0aW9ucy5hbGxvd0Zyb20oYmFja2dyb3VuZFRhc2tzU2VjdXJpdHlHcm91cCwgUG9ydC50Y3AoNDQzKSk7XG4gICAgZWNzLmNvbm5lY3Rpb25zLmFsbG93RnJvbShiYWNrZ3JvdW5kVGFza3NTZWN1cml0eUdyb3VwLCBQb3J0LnRjcCg0NDMpKTtcbiAgICBlY3NBZ2VudC5jb25uZWN0aW9ucy5hbGxvd0Zyb20oYmFja2dyb3VuZFRhc2tzU2VjdXJpdHlHcm91cCwgUG9ydC50Y3AoNDQzKSk7XG4gICAgZWNzVGVsZW1ldHJ5LmNvbm5lY3Rpb25zLmFsbG93RnJvbShiYWNrZ3JvdW5kVGFza3NTZWN1cml0eUdyb3VwLCBQb3J0LnRjcCg0NDMpKTtcbiAgICBzbS5jb25uZWN0aW9ucy5hbGxvd0Zyb20oYmFja2dyb3VuZFRhc2tzU2VjdXJpdHlHcm91cCwgUG9ydC50Y3AoNDQzKSk7XG4gICAgY3cuY29ubmVjdGlvbnMuYWxsb3dGcm9tKGJhY2tncm91bmRUYXNrc1NlY3VyaXR5R3JvdXAsIFBvcnQudGNwKDQ0MykpO1xuXG4gICAgY29uc3QgcmVkaXNTZWN1cml0eUdyb3VwID0gbmV3IFNlY3VyaXR5R3JvdXAodGhpcywgJ3JlZGlzLVNHJywge1xuICAgICAgdnBjLFxuICAgICAgZGVzY3JpcHRpb246ICdTZWN1cml0eUdyb3VwIGFzc29jaWF0ZWQgd2l0aCB0aGUgRWxhc3RpQ2FjaGUgUmVkaXMgQ2x1c3RlcicsXG4gICAgICBhbGxvd0FsbE91dGJvdW5kOiBmYWxzZVxuICAgIH0pO1xuICAgIHJlZGlzU2VjdXJpdHlHcm91cC5jb25uZWN0aW9ucy5hbGxvd0Zyb20oYXBwbGljYXRpb25TZWN1cml0eUdyb3VwLCBQb3J0LnRjcCg2Mzc5KSwgJ0FwcGxpY2F0aW9uIGluZ3Jlc3MgNjM3OScpO1xuICAgIHJlZGlzU2VjdXJpdHlHcm91cC5jb25uZWN0aW9ucy5hbGxvd0Zyb20oYmFja2dyb3VuZFRhc2tzU2VjdXJpdHlHcm91cCwgUG9ydC50Y3AoNjM3OSksICdTY2hlZHVsZXIgaW5ncmVzcyA2Mzc5Jyk7XG5cbiAgICAvLyBQYXJhbWV0ZXJzXG4gICAgY29uc3QgTE9HX0xFVkVMID0gbmV3IFN0cmluZ1BhcmFtZXRlcih0aGlzLCAnUGFyYW1ldGVyJywge1xuICAgICAgYWxsb3dlZFBhdHRlcm46ICcuKicsXG4gICAgICBkZXNjcmlwdGlvbjogJ0FwcGxpY2F0aW9uIGxvZyBsZXZlbCcsXG4gICAgICBwYXJhbWV0ZXJOYW1lOiAnTE9HX0xFVkVMJyxcbiAgICAgIHN0cmluZ1ZhbHVlOiAnZGVidWcnLFxuICAgICAgdGllcjogUGFyYW1ldGVyVGllci5TVEFOREFSRCxcbiAgICB9KS5zdHJpbmdWYWx1ZTtcblxuICAgIGNvbnN0IEFQUF9VUkwgPSBTdHJpbmdQYXJhbWV0ZXIuZnJvbVN0cmluZ1BhcmFtZXRlck5hbWUodGhpcywgJ0FQUF9VUkwnLCAnQVBQX1VSTCcpLnN0cmluZ1ZhbHVlO1xuXG4gICAgLy8gUkRTXG4gICAgY29uc3QgZGF0YWJhc2VTZWN1cml0eUdyb3VwID0gbmV3IFNlY3VyaXR5R3JvdXAodGhpcywgJ2RhdGFiYXNlLVNHJywge1xuICAgICAgdnBjLFxuICAgICAgZGVzY3JpcHRpb246ICdTZWN1cml0eUdyb3VwIGFzc29jaWF0ZWQgd2l0aCB0aGUgTXlTUUwgUkRTIEluc3RhbmNlJyxcbiAgICAgIGFsbG93QWxsT3V0Ym91bmQ6IGZhbHNlXG4gICAgfSk7XG4gICAgZGF0YWJhc2VTZWN1cml0eUdyb3VwLmNvbm5lY3Rpb25zLmFsbG93RnJvbShhcHBsaWNhdGlvblNlY3VyaXR5R3JvdXAsIFBvcnQudGNwKDMzMDYpKTtcbiAgICBkYXRhYmFzZVNlY3VyaXR5R3JvdXAuY29ubmVjdGlvbnMuYWxsb3dGcm9tKGJhY2tncm91bmRUYXNrc1NlY3VyaXR5R3JvdXAsIFBvcnQudGNwKDMzMDYpKTtcblxuICAgIGNvbnN0IGRiID0gbmV3IERhdGFiYXNlSW5zdGFuY2UodGhpcywgJ3ByaW1hcnktZGInLCB7XG4gICAgICBhbGxvY2F0ZWRTdG9yYWdlOiAyMCxcbiAgICAgIGF1dG9NaW5vclZlcnNpb25VcGdyYWRlOiB0cnVlLFxuICAgICAgYWxsb3dNYWpvclZlcnNpb25VcGdyYWRlOiBmYWxzZSxcbiAgICAgIGRhdGFiYXNlTmFtZTogJ2V4YW1wbGUnLFxuICAgICAgZW5naW5lOiBEYXRhYmFzZUluc3RhbmNlRW5naW5lLm15c3FsKHtcbiAgICAgICAgdmVyc2lvbjogTXlzcWxFbmdpbmVWZXJzaW9uLlZFUl84XzBfMjFcbiAgICAgIH0pLFxuICAgICAgaWFtQXV0aGVudGljYXRpb246IHRydWUsXG4gICAgICBpbnN0YW5jZVR5cGU6IEluc3RhbmNlVHlwZS5vZihJbnN0YW5jZUNsYXNzLkJVUlNUQUJMRTMsIEluc3RhbmNlU2l6ZS5TTUFMTCksXG4gICAgICBtYXhBbGxvY2F0ZWRTdG9yYWdlOiAyNTAsXG4gICAgICBtdWx0aUF6OiBmYWxzZSxcbiAgICAgIHNlY3VyaXR5R3JvdXBzOiBbZGF0YWJhc2VTZWN1cml0eUdyb3VwXSxcbiAgICAgIHZwYyxcbiAgICAgIHZwY1N1Ym5ldHM6IHtcbiAgICAgICAgc3VibmV0R3JvdXBOYW1lOiBTVUJORVRfSVNPTEFURUQubmFtZVxuICAgICAgfVxuICAgIH0pO1xuXG4gICAgLy8gRUxBU1RJQ0FDSEVcbiAgICBjb25zdCByZWRpc1N1Ym5ldEdyb3VwID0gbmV3IENmblN1Ym5ldEdyb3VwKHRoaXMsICdyZWRpcy1zdWJuZXQtZ3JvdXAnLCB7XG4gICAgICBkZXNjcmlwdGlvbjogJ1JlZGlzIFN1Ym5ldCBHcm91cCcsXG4gICAgICBzdWJuZXRJZHM6IHZwYy5pc29sYXRlZFN1Ym5ldHMubWFwKHMgPT4gcy5zdWJuZXRJZCksXG4gICAgICBjYWNoZVN1Ym5ldEdyb3VwTmFtZTogJ1JlZGlzU3VibmV0R3JvdXAnXG4gICAgfSk7XG5cbiAgICBjb25zdCByZWRpcyA9IG5ldyBDZm5DYWNoZUNsdXN0ZXIodGhpcywgJ3JlZGlzLWNsdXN0ZXInLCB7XG4gICAgICBjYWNoZU5vZGVUeXBlOiAnY2FjaGUudDMuc21hbGwnLFxuICAgICAgY2FjaGVTdWJuZXRHcm91cE5hbWU6IHJlZGlzU3VibmV0R3JvdXAuY2FjaGVTdWJuZXRHcm91cE5hbWUsXG4gICAgICBjbHVzdGVyTmFtZTogJ3JlZGlzLWNsdXN0ZXInLFxuICAgICAgZW5naW5lOiAncmVkaXMnLFxuICAgICAgZW5naW5lVmVyc2lvbjogJzYueCcsXG4gICAgICBudW1DYWNoZU5vZGVzOiAxLFxuICAgICAgcG9ydDogNjM3OSxcbiAgICAgIHZwY1NlY3VyaXR5R3JvdXBJZHM6IFtyZWRpc1NlY3VyaXR5R3JvdXAuc2VjdXJpdHlHcm91cElkXVxuICAgIH0pO1xuXG4gICAgcmVkaXMubm9kZS5hZGREZXBlbmRlbmN5KHJlZGlzU3VibmV0R3JvdXApO1xuXG4gICAgLy8gU0VDUkVUU1xuICAgIGNvbnN0IHN0cmlwZSA9IFNlY3JldE1hbmFnZXIuZnJvbVNlY3JldE5hbWVWMih0aGlzLCAnc3RyaXBlX2tleXMnLCAnU1RSSVBFJyk7IC8vIERvbid0IGZvcmdldCB0byBjcmVhdGUgdGhpcyBtYW51YWxseVxuXG4gICAgY29uc3Qgc2VjcmV0cyA9IHtcbiAgICAgIERCX0RBVEFCQVNFOiBTZWNyZXQuZnJvbVNlY3JldHNNYW5hZ2VyKGRiLnNlY3JldCEsICdkYm5hbWUnKSxcbiAgICAgIERCX1VTRVJOQU1FOiBTZWNyZXQuZnJvbVNlY3JldHNNYW5hZ2VyKGRiLnNlY3JldCEsICd1c2VybmFtZScpLFxuICAgICAgREJfUEFTU1dPUkQ6IFNlY3JldC5mcm9tU2VjcmV0c01hbmFnZXIoZGIuc2VjcmV0ISwgJ3Bhc3N3b3JkJyksXG4gICAgICBTVFJJUEVfS0VZOiBTZWNyZXQuZnJvbVNlY3JldHNNYW5hZ2VyKHN0cmlwZSwgJ1NUUklQRV9LRVknKSxcbiAgICAgIFNUUklQRV9TRUNSRVQ6IFNlY3JldC5mcm9tU2VjcmV0c01hbmFnZXIoc3RyaXBlLCAnU1RSSVBFX1NFQ1JFVCcpLFxuICAgIH07XG5cbiAgICAvLyBUaGlzIGlzIHNwZWNpZmljIGZvciBsYXJhdmVsIGFwcGxpY2F0aW9uIHVzZWQgaW4gZXhhbXBsZXNcbiAgICBjb25zdCBlbnZpcm9ubWVudCA9IHtcbiAgICAgIEFQUF9VUkwsXG4gICAgICBMT0dfQ0hBTk5FTDogJ3N0ZG91dCcsXG4gICAgICBMT0dfTEVWRUwsXG4gICAgICBEQl9DT05ORUNUSU9OOiAnbXlzcWwnLFxuICAgICAgREJfSE9TVDogZGIuZGJJbnN0YW5jZUVuZHBvaW50QWRkcmVzcyxcbiAgICAgIERCX1BPUlQ6IGRiLmRiSW5zdGFuY2VFbmRwb2ludFBvcnQsXG4gICAgICBDQUNIRV9EUklWRVI6ICdyZWRpcycsXG4gICAgICBSRURJU19IT1NUOiByZWRpcy5hdHRyUmVkaXNFbmRwb2ludEFkZHJlc3MsXG4gICAgICBSRURJU19QQVNTV09SRDogJ251bGwnLFxuICAgICAgUkVESVNfUE9SVDogJzYzNzknLFxuICAgIH07XG5cbiAgICBjb25zdCBhcHBsaWNhdGlvbkNvbnRhaW5lciA9IGFwcGxpY2F0aW9uU2VydmljZURlZmluaXRpb24uYWRkQ29udGFpbmVyKCdhcHAtY29udGFpbmVyJywge1xuICAgICAgY3B1OiAyNTYsXG4gICAgICBlbnZpcm9ubWVudCxcbiAgICAgIGVzc2VudGlhbDogdHJ1ZSxcbiAgICAgIGltYWdlOiBDb250YWluZXJJbWFnZS5mcm9tRG9ja2VySW1hZ2VBc3NldChhcHBsaWNhdGlvbkltYWdlKSxcbiAgICAgIGxvZ2dpbmc6IExvZ0RyaXZlci5hd3NMb2dzKHtcbiAgICAgICAgbG9nR3JvdXA6IGFwcGxpY2F0aW9uTG9nR3JvdXAsXG4gICAgICAgIHN0cmVhbVByZWZpeDogbmV3IERhdGUoKS50b0xvY2FsZURhdGVTdHJpbmcoJ2VuLVpBJylcbiAgICAgIH0pLFxuICAgICAgbWVtb3J5TGltaXRNaUI6IDUxMixcbiAgICAgIHNlY3JldHMsXG4gICAgfSk7XG5cbiAgICBhcHBsaWNhdGlvbkNvbnRhaW5lci5hZGRQb3J0TWFwcGluZ3Moe1xuICAgICAgY29udGFpbmVyUG9ydDogODAsXG4gICAgICBob3N0UG9ydDogODAsXG4gICAgICBwcm90b2NvbDogRWNzUHJvdG9jb2wuVENQXG4gICAgfSk7XG5cbiAgICBjb25zdCBhcHBsaWNhdGlvblNlcnZpY2UgPSBuZXcgRmFyZ2F0ZVNlcnZpY2UodGhpcywgJ2FwcGxpY2F0aW9uLWZhcmdhdGUtc2VydmljZScsIHtcbiAgICAgIGFzc2lnblB1YmxpY0lwOiB0cnVlLFxuICAgICAgY2lyY3VpdEJyZWFrZXI6IHtcbiAgICAgICAgcm9sbGJhY2s6IHRydWVcbiAgICAgIH0sXG4gICAgICBkZXBsb3ltZW50Q29udHJvbGxlcjoge1xuICAgICAgICB0eXBlOiBEZXBsb3ltZW50Q29udHJvbGxlclR5cGUuRUNTXG4gICAgICB9LFxuICAgICAgZGVzaXJlZENvdW50OiAxLFxuICAgICAgY2x1c3RlcixcbiAgICAgIHBsYXRmb3JtVmVyc2lvbjogRmFyZ2F0ZVBsYXRmb3JtVmVyc2lvbi5MQVRFU1QsXG4gICAgICBzZWN1cml0eUdyb3VwczogW2FwcGxpY2F0aW9uU2VjdXJpdHlHcm91cF0sXG4gICAgICB0YXNrRGVmaW5pdGlvbjogYXBwbGljYXRpb25TZXJ2aWNlRGVmaW5pdGlvbixcbiAgICAgIHZwY1N1Ym5ldHM6IHtcbiAgICAgICAgc3VibmV0R3JvdXBOYW1lOiBTVUJORVRfQVBQTElDQVRJT04ubmFtZVxuICAgICAgfVxuICAgIH0pO1xuXG4gICAgYXBwbGljYXRpb25TZXJ2aWNlLmF0dGFjaFRvQXBwbGljYXRpb25UYXJnZXRHcm91cCh0YXJnZXRHcm91cEh0dHApO1xuXG4gICAgY29uc3Qgc2NhbGVUYXJnZXQgPSBhcHBsaWNhdGlvblNlcnZpY2UuYXV0b1NjYWxlVGFza0NvdW50KHtcbiAgICAgIG1pbkNhcGFjaXR5OiAxLFxuICAgICAgbWF4Q2FwYWNpdHk6IDEwLFxuICAgIH0pO1xuXG4gICAgc2NhbGVUYXJnZXQuc2NhbGVPbk1lbW9yeVV0aWxpemF0aW9uKCdzY2FsZS1vdXQtbWVtb3J5LXRocmVzaG9sZCcsIHtcbiAgICAgIHRhcmdldFV0aWxpemF0aW9uUGVyY2VudDogNzVcbiAgICB9KTtcbiAgICBzY2FsZVRhcmdldC5zY2FsZU9uQ3B1VXRpbGl6YXRpb24oJ3NjYWxlLW91dC1jcHUtdGhyZXNob2xkJywge1xuICAgICAgdGFyZ2V0VXRpbGl6YXRpb25QZXJjZW50OiA3NVxuICAgIH0pO1xuXG4gICAgLy8gU2NoZWR1bGVkIFRhc2tzXG4gICAgY29uc3Qgc2NoZWR1bGVkU2VydmljZVJvbGUgPSBuZXcgUm9sZSh0aGlzLCAnc2NoZWR1bGVkLWZhcmdhdGUtdGFzay1yb2xlJywge1xuICAgICAgYXNzdW1lZEJ5OiBuZXcgU2VydmljZVByaW5jaXBhbCgnZWNzLXRhc2tzLmFtYXpvbmF3cy5jb20nKSxcbiAgICAgIHJvbGVOYW1lOiAnc2NoZWR1bGVkLWZhcmdhdGUtdGFzay1yb2xlJyxcbiAgICAgIGRlc2NyaXB0aW9uOiAnUm9sZSB0aGF0IHRoZSBzY2hlZHVsZWQgdGFzayBkZWZpbml0aW9ucyB1c2UgdG8gcnVuIHNjaGVkdWxlZCBqb2JzJyxcbiAgICB9KTtcblxuICAgIGNvbnN0IHNjaGVkdWxlZFNlcnZpY2VEZWZpbml0aW9uID0gbmV3IFRhc2tEZWZpbml0aW9uKHRoaXMsICdiYWNrZ3JvdW5kLWZhcmdhdGUtc2VydmljZS1kZWZpbml0aW9uJywge1xuICAgICAgY29tcGF0aWJpbGl0eTogQ29tcGF0aWJpbGl0eS5FQzJfQU5EX0ZBUkdBVEUsXG4gICAgICBjcHU6ICcyNTYnLFxuICAgICAgZmFtaWx5OiAnYmFja2dyb3VuZC10YXNrLWZhbWlseScsXG4gICAgICBtZW1vcnlNaUI6ICc1MTInLFxuICAgICAgdGFza1JvbGU6IHNjaGVkdWxlZFNlcnZpY2VSb2xlXG4gICAgfSk7XG5cbiAgICAvLyBXZSBkb24ndCB3YW50IHRvIGF1dG9zY2FsZSBzY2hlZHVsZWQgdGFza3MuIE90aGVyd2lzZSBlYWNoIGNvbnRhaW5lciB3aWxsIHJ1biBlYWNoIGpvYiBpbmRlcGVuZGVudGx5XG4gICAgLy8gSWYgc2NoZWR1bGVkIGpvYnMgYXJlIHNsb3cgcnVubmluZyB5b3UgYXJlIGJldHRlciBvZmYgcHVzaGluZyB0aGUgd29yayB0byB0aGUgcXVldWVcbiAgICBjb25zdCBzY2hlZHVsZWRTZXJ2aWNlID0gbmV3IEZhcmdhdGVTZXJ2aWNlKHRoaXMsICdzY2hlZHVsZWQtZmFyZ2F0ZS1zZXJ2aWNlJywge1xuICAgICAgYXNzaWduUHVibGljSXA6IHRydWUsXG4gICAgICBjaXJjdWl0QnJlYWtlcjoge1xuICAgICAgICByb2xsYmFjazogdHJ1ZVxuICAgICAgfSxcbiAgICAgIGRlcGxveW1lbnRDb250cm9sbGVyOiB7XG4gICAgICAgIHR5cGU6IERlcGxveW1lbnRDb250cm9sbGVyVHlwZS5FQ1NcbiAgICAgIH0sXG4gICAgICBkZXNpcmVkQ291bnQ6IDEsXG4gICAgICBjbHVzdGVyOiBiYWNrZ3JvdW5kQ2x1c3RlcixcbiAgICAgIHBsYXRmb3JtVmVyc2lvbjogRmFyZ2F0ZVBsYXRmb3JtVmVyc2lvbi5MQVRFU1QsXG4gICAgICBzZWN1cml0eUdyb3VwczogW2JhY2tncm91bmRUYXNrc1NlY3VyaXR5R3JvdXBdLFxuICAgICAgdGFza0RlZmluaXRpb246IHNjaGVkdWxlZFNlcnZpY2VEZWZpbml0aW9uLFxuICAgICAgdnBjU3VibmV0czoge1xuICAgICAgICBzdWJuZXRHcm91cE5hbWU6IFNVQk5FVF9CQUNLR1JPVU5EX1RBU0tTLm5hbWVcbiAgICAgIH1cbiAgICB9KTtcblxuICAgIHNjaGVkdWxlZFNlcnZpY2UudGFza0RlZmluaXRpb24uYWRkQ29udGFpbmVyKCdiYWNrZ3JvdW5kLWNvbnRhaW5lcicsIHtcbiAgICAgIGNwdTogMjU2LFxuICAgICAgZW52aXJvbm1lbnQsXG4gICAgICBlc3NlbnRpYWw6IHRydWUsXG4gICAgICBpbWFnZTogQ29udGFpbmVySW1hZ2UuZnJvbURvY2tlckltYWdlQXNzZXQoc2NoZWR1bGVySW1hZ2UpLFxuICAgICAgbG9nZ2luZzogTG9nRHJpdmVyLmF3c0xvZ3Moe1xuICAgICAgICBsb2dHcm91cDogc2NoZWR1bGVyTG9nR3JvdXAsXG4gICAgICAgIHN0cmVhbVByZWZpeDogbmV3IERhdGUoKS50b0xvY2FsZURhdGVTdHJpbmcoJ2VuLVpBJyksXG4gICAgICB9KSxcbiAgICAgIG1lbW9yeUxpbWl0TWlCOiA1MTIsXG4gICAgICBzZWNyZXRzLFxuICAgIH0pO1xuXG4gICAgLy8gU1FTIGFuZCBRdWV1ZVByb2Nlc3NpbmdTZXJ2aWNlXG4gICAgY29uc3Qgc2NoZWR1bGVySm9iUXVldWUgPSBuZXcgUXVldWUodGhpcywgJ2pvYi1xdWV1ZScsIHtcbiAgICAgIHF1ZXVlTmFtZTogJ3NjaGVkdWxlci1qb2ItcXVldWUnXG4gICAgfSk7XG5cbiAgICBjb25zdCBzcXNQb2xpY3kgPSBuZXcgUG9saWN5KHRoaXMsICdmYXJnYXRlLXRhc2stc3FzLXBvbGljeScsIHtcbiAgICAgIHN0YXRlbWVudHM6IFtcbiAgICAgICAgbmV3IFBvbGljeVN0YXRlbWVudCh7XG4gICAgICAgICAgZWZmZWN0OiBFZmZlY3QuQUxMT1csXG4gICAgICAgICAgYWN0aW9uczogWydzcXM6KiddLFxuICAgICAgICAgIHJlc291cmNlczogW3NjaGVkdWxlckpvYlF1ZXVlLnF1ZXVlQXJuXSxcbiAgICAgICAgfSksXG4gICAgICBdLFxuICAgIH0pO1xuXG4gICAgY29uc3QgcXVldWVXb3JrZXJTZXJ2aWNlID0gbmV3IFF1ZXVlUHJvY2Vzc2luZ0ZhcmdhdGVTZXJ2aWNlKHRoaXMsICdxdWV1ZWQtam9icycsIHtcbiAgICAgIGFzc2lnblB1YmxpY0lwOiBmYWxzZSxcbiAgICAgIGNpcmN1aXRCcmVha2VyOiB7XG4gICAgICAgIHJvbGxiYWNrOiB0cnVlXG4gICAgICB9LFxuICAgICAgY2x1c3RlcjogYmFja2dyb3VuZENsdXN0ZXIsXG4gICAgICBjcHU6IDI1NixcbiAgICAgIGRlcGxveW1lbnRDb250cm9sbGVyOiB7XG4gICAgICAgIHR5cGU6IERlcGxveW1lbnRDb250cm9sbGVyVHlwZS5FQ1NcbiAgICAgIH0sXG4gICAgICBlbmFibGVMb2dnaW5nOiB0cnVlLFxuICAgICAgZW52aXJvbm1lbnQsXG4gICAgICBpbWFnZTogQ29udGFpbmVySW1hZ2UuZnJvbURvY2tlckltYWdlQXNzZXQocXVldWVXb3JrZXJJbWFnZSksXG4gICAgICBsb2dEcml2ZXI6IExvZ0RyaXZlci5hd3NMb2dzKHtcbiAgICAgICAgbG9nR3JvdXA6IHF1ZXVlV29ya2VyTG9nR3JvdXAsXG4gICAgICAgIHN0cmVhbVByZWZpeDogbmV3IERhdGUoKS50b0xvY2FsZURhdGVTdHJpbmcoJ2VuLVpBJylcbiAgICAgIH0pLFxuICAgICAgbWF4U2NhbGluZ0NhcGFjaXR5OiAyLFxuICAgICAgbWVtb3J5TGltaXRNaUI6IDUxMixcbiAgICAgIHF1ZXVlOiBzY2hlZHVsZXJKb2JRdWV1ZSxcbiAgICAgIHNlY3JldHMsXG4gICAgICBwbGF0Zm9ybVZlcnNpb246IEZhcmdhdGVQbGF0Zm9ybVZlcnNpb24uTEFURVNULFxuICAgICAgc2VjdXJpdHlHcm91cHM6IFtiYWNrZ3JvdW5kVGFza3NTZWN1cml0eUdyb3VwXSxcbiAgICAgIHRhc2tTdWJuZXRzOiB7XG4gICAgICAgIHN1Ym5ldEdyb3VwTmFtZTogU1VCTkVUX0JBQ0tHUk9VTkRfVEFTS1MubmFtZVxuICAgICAgfVxuICAgIH0pO1xuXG4gICAgLy8gQWxsb3cgRUNTIHRvIGdyYWIgdGhlIGltYWdlcyB0byBzcGluIHVwIG5ldyBjb250YWluZXJzXG4gICAgYXBwbGljYXRpb25JbWFnZS5yZXBvc2l0b3J5LmdyYW50UHVsbChhcHBsaWNhdGlvblNlcnZpY2UudGFza0RlZmluaXRpb24ub2J0YWluRXhlY3V0aW9uUm9sZSgpKTtcbiAgICBzY2hlZHVsZXJJbWFnZS5yZXBvc2l0b3J5LmdyYW50UHVsbChzY2hlZHVsZWRTZXJ2aWNlLnRhc2tEZWZpbml0aW9uLm9idGFpbkV4ZWN1dGlvblJvbGUoKSk7XG4gICAgcXVldWVXb3JrZXJJbWFnZS5yZXBvc2l0b3J5LmdyYW50UHVsbChxdWV1ZVdvcmtlclNlcnZpY2UudGFza0RlZmluaXRpb24ub2J0YWluRXhlY3V0aW9uUm9sZSgpKTtcblxuICAgIC8vIFNRUyBQZXJtaXNzaW9uc1xuICAgIHNxc0VuZHBvaW50LmNvbm5lY3Rpb25zLmFsbG93RnJvbShiYWNrZ3JvdW5kVGFza3NTZWN1cml0eUdyb3VwLCBQb3J0LnRjcCg0NDMpKTtcbiAgICBzcXNFbmRwb2ludC5jb25uZWN0aW9ucy5hbGxvd0Zyb20oYXBwbGljYXRpb25TZWN1cml0eUdyb3VwLCBQb3J0LnRjcCg0NDMpKTtcblxuICAgIC8vIEFwcGxpY2F0aW9uIFBlcm1pc3Npb25zIGdyYW50c1xuICAgIHRhc2tSb2xlLmF0dGFjaElubGluZVBvbGljeShzcXNQb2xpY3kpO1xuICAgIHNjaGVkdWxlZFNlcnZpY2VSb2xlLmF0dGFjaElubGluZVBvbGljeShzcXNQb2xpY3kpO1xuICAgIHF1ZXVlV29ya2VyU2VydmljZS50YXNrRGVmaW5pdGlvbi50YXNrUm9sZS5hdHRhY2hJbmxpbmVQb2xpY3koc3FzUG9saWN5KTtcblxuICAgIHNjaGVkdWxlckpvYlF1ZXVlLmdyYW50U2VuZE1lc3NhZ2VzKGFwcGxpY2F0aW9uU2VydmljZS50YXNrRGVmaW5pdGlvbi5vYnRhaW5FeGVjdXRpb25Sb2xlKCkpO1xuICAgIHNjaGVkdWxlckpvYlF1ZXVlLmdyYW50U2VuZE1lc3NhZ2VzKHNjaGVkdWxlZFNlcnZpY2UudGFza0RlZmluaXRpb24ub2J0YWluRXhlY3V0aW9uUm9sZSgpKTtcblxuICAgIHNjaGVkdWxlckpvYlF1ZXVlLmdyYW50U2VuZE1lc3NhZ2VzKHF1ZXVlV29ya2VyU2VydmljZS50YXNrRGVmaW5pdGlvbi5vYnRhaW5FeGVjdXRpb25Sb2xlKCkpO1xuICAgIHNjaGVkdWxlckpvYlF1ZXVlLmdyYW50UHVyZ2UocXVldWVXb3JrZXJTZXJ2aWNlLnRhc2tEZWZpbml0aW9uLm9idGFpbkV4ZWN1dGlvblJvbGUoKSk7XG4gICAgc2NoZWR1bGVySm9iUXVldWUuZ3JhbnRDb25zdW1lTWVzc2FnZXMocXVldWVXb3JrZXJTZXJ2aWNlLnRhc2tEZWZpbml0aW9uLm9idGFpbkV4ZWN1dGlvblJvbGUoKSk7XG5cbiAgICAvLyBTRUNSRVRTIFBFUk1JU1NJT05TXG4gICAgT2JqZWN0LnZhbHVlcyhzZWNyZXRzKS5mb3JFYWNoKHNlY3JldCA9PiB7XG4gICAgICBzZWNyZXQuZ3JhbnRSZWFkKGFwcGxpY2F0aW9uU2VydmljZS50YXNrRGVmaW5pdGlvbi5vYnRhaW5FeGVjdXRpb25Sb2xlKCkpO1xuICAgICAgc2VjcmV0LmdyYW50UmVhZChzY2hlZHVsZWRTZXJ2aWNlLnRhc2tEZWZpbml0aW9uLm9idGFpbkV4ZWN1dGlvblJvbGUoKSk7XG4gICAgICBzZWNyZXQuZ3JhbnRSZWFkKHF1ZXVlV29ya2VyU2VydmljZS50YXNrRGVmaW5pdGlvbi5vYnRhaW5FeGVjdXRpb25Sb2xlKCkpO1xuICAgIH0pO1xuXG4gICAgLy8gTG9nIFBlcm1pc3Npb25zXG4gICAgYXBwbGljYXRpb25Mb2dHcm91cC5ncmFudChhcHBsaWNhdGlvblNlcnZpY2UudGFza0RlZmluaXRpb24ub2J0YWluRXhlY3V0aW9uUm9sZSgpLCAnbG9nczpDcmVhdGVMb2dTdHJlYW0nKTtcbiAgICBhcHBsaWNhdGlvbkxvZ0dyb3VwLmdyYW50KGFwcGxpY2F0aW9uU2VydmljZS50YXNrRGVmaW5pdGlvbi5vYnRhaW5FeGVjdXRpb25Sb2xlKCksICdsb2dzOlB1dExvZ0V2ZW50cycpO1xuICAgIHNjaGVkdWxlckxvZ0dyb3VwLmdyYW50KHNjaGVkdWxlZFNlcnZpY2UudGFza0RlZmluaXRpb24ub2J0YWluRXhlY3V0aW9uUm9sZSgpLCAnbG9nczpDcmVhdGVMb2dTdHJlYW0nKTtcbiAgICBzY2hlZHVsZXJMb2dHcm91cC5ncmFudChzY2hlZHVsZWRTZXJ2aWNlLnRhc2tEZWZpbml0aW9uLm9idGFpbkV4ZWN1dGlvblJvbGUoKSwgJ2xvZ3M6UHV0TG9nRXZlbnRzJyk7XG4gICAgcXVldWVXb3JrZXJMb2dHcm91cC5ncmFudChxdWV1ZVdvcmtlclNlcnZpY2UudGFza0RlZmluaXRpb24ub2J0YWluRXhlY3V0aW9uUm9sZSgpLCAnbG9nczpDcmVhdGVMb2dTdHJlYW0nKTtcbiAgICBxdWV1ZVdvcmtlckxvZ0dyb3VwLmdyYW50KHF1ZXVlV29ya2VyU2VydmljZS50YXNrRGVmaW5pdGlvbi5vYnRhaW5FeGVjdXRpb25Sb2xlKCksICdsb2dzOlB1dExvZ0V2ZW50cycpO1xuXG4gICAgLy8gREIgcGVybWlzc2lvbnNcbiAgICBkYi5ncmFudENvbm5lY3QoYXBwbGljYXRpb25TZXJ2aWNlLnRhc2tEZWZpbml0aW9uLnRhc2tSb2xlKTtcbiAgICBkYi5ncmFudENvbm5lY3Qoc2NoZWR1bGVkU2VydmljZS50YXNrRGVmaW5pdGlvbi50YXNrUm9sZSk7XG4gICAgZGIuZ3JhbnRDb25uZWN0KHF1ZXVlV29ya2VyU2VydmljZS50YXNrRGVmaW5pdGlvbi50YXNrUm9sZSk7XG5cbiAgICAvLyBDcmVhdGUgYW4gQWNjZWxlcmF0b3JcbiAgICBjb25zdCBhY2NlbGVyYXRvciA9IG5ldyBBY2NlbGVyYXRvcih0aGlzLCAnZ2xvYmFsLWFjY2VsZXJhdG9yJyk7XG5cbiAgICAvLyBDcmVhdGUgYSBMaXN0ZW5lclxuICAgIGNvbnN0IGFjY2VsZXJhdG9yTGlzdGVuZXIgPSBhY2NlbGVyYXRvci5hZGRMaXN0ZW5lcignZ2xvYmFsLWFjY2VsZXJhdG9yLWxpc3RlbmVyJywge1xuICAgICAgcG9ydFJhbmdlczogW1xuICAgICAgICB7IGZyb21Qb3J0OiA4MCB9LFxuICAgICAgICB7IGZyb21Qb3J0OiA0NDMgfSxcbiAgICAgIF0sXG4gICAgfSk7XG5cbiAgICBjb25zdCBlbmRwb2ludEdyb3VwID0gYWNjZWxlcmF0b3JMaXN0ZW5lci5hZGRFbmRwb2ludEdyb3VwKCdnbG9iYWwtYWNjZWxlcmF0b3ItbGlzdGVuZXItYWxiLWdyb3VwJywge1xuICAgICAgZW5kcG9pbnRzOiBbXG4gICAgICAgIG5ldyBBcHBsaWNhdGlvbkxvYWRCYWxhbmNlckVuZHBvaW50KGFsYiwge1xuICAgICAgICAgIHByZXNlcnZlQ2xpZW50SXA6IHRydWUsXG4gICAgICAgIH0pXG4gICAgICBdLFxuICAgICAgaGVhbHRoQ2hlY2tJbnRlcnZhbDogRHVyYXRpb24uc2Vjb25kcygzMCksXG4gICAgICBoZWFsdGhDaGVja1BhdGg6ICcvYXBpL2hlYWx0aC1jaGVjaydcbiAgICB9KTtcblxuICAgIC8vIFJlbWVtYmVyIHRoYXQgdGhlcmUgaXMgb25seSBvbmUgQUdBIHNlY3VyaXR5IGdyb3VwIHBlciBWUEMuXG4gICAgY29uc3QgYWNjZWxlcmF0b3JTZWN1cml0eUdyb3VwID0gZW5kcG9pbnRHcm91cC5jb25uZWN0aW9uc1BlZXIoJ0dsb2JhbEFjY2VsZXJhdG9yU0cnLCB2cGMpO1xuXG4gICAgLy8gQWxsb3cgY29ubmVjdGlvbnMgZnJvbSB0aGUgQUdBIHRvIHRoZSBBTEJcbiAgICBhbGIuY29ubmVjdGlvbnMuYWxsb3dGcm9tKGFjY2VsZXJhdG9yU2VjdXJpdHlHcm91cCwgUG9ydC50Y3AoNDQzKSk7XG4gIH1cbn1cbiJdfQ==